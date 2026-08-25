<?php
/**
 * RT-315 Finder Report application workflow tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\FinderReport\CleanupFinderReportEvidence;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceDerivative;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceImageProcessor;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceSource;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceSourceInspector;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceSourceMetadata;
use ReturnTag\TagCore\Application\FinderReport\FinderReportMessageProtector;
use ReturnTag\TagCore\Application\FinderReport\FinderReportProcessingScheduler;
use ReturnTag\TagCore\Application\FinderReport\FinderReportRateLimiter;
use ReturnTag\TagCore\Application\FinderReport\FinderReportSubmissionException;
use ReturnTag\TagCore\Application\FinderReport\FinderReportSubmissionInput;
use ReturnTag\TagCore\Application\FinderReport\PrivateMediaObject;
use ReturnTag\TagCore\Application\FinderReport\PrivateMediaStorage;
use ReturnTag\TagCore\Application\FinderReport\ProcessedFinderEvidence;
use ReturnTag\TagCore\Application\FinderReport\ProcessFinderReportEvidence;
use ReturnTag\TagCore\Application\FinderReport\SubmitFinderReport;
use ReturnTag\TagCore\Application\Persistence\Record\FinderReportMediaRecord;
use ReturnTag\TagCore\Application\Persistence\Record\FinderReportRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewFinderReportMediaRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewFinderReportRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportMediaRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportRepository;
use ReturnTag\TagCore\Application\Persistence\Value\FinderReportMessageCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDerivative;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDigest;
use ReturnTag\TagCore\Application\Persistence\Value\PrivateMediaReferenceCiphertext;
use ReturnTag\TagCore\Application\PublicTag\PublicTagStateReader;
use ReturnTag\TagCore\Application\PublicTag\PublicTagStateRecord;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceMime;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderReportStatus;
use ReturnTag\TagCore\Domain\FinderReport\PrivateMediaObjectKind;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\ImmediateTransactionManager;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryEventRepository;
use RuntimeException;

/** Verifies durable intake, fail-closed processing, and bounded cleanup. */
final class FinderReportWorkflowTest extends TestCase {
	/**
	 * Fixed UTC time shared by workflow fixtures.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $now;

	/** Establish one deterministic UTC clock. */
	protected function setUp(): void {
		$this->now = new DateTimeImmutable( '2026-08-04 06:00:00', new DateTimeZone( 'UTC' ) );
	}

	/** A valid anonymous report persists before scheduling internal work. */
	public function test_submission_persists_private_state_before_queueing(): void {
		$reports = $this->createMock( FinderReportRepository::class );
		$reports->expects( self::once() )
			->method( 'insert' )
			->willReturnCallback(
				static fn( NewFinderReportRecord $record ): FinderReportRecord => new FinderReportRecord( 10, $record )
			);
		$media = $this->createMock( FinderReportMediaRepository::class );
		$media->expects( self::once() )
			->method( 'insert' )
			->with(
				self::callback(
					static fn( NewFinderReportMediaRecord $record ): bool => 10 === $record->finder_report_id
						&& FinderEvidenceStatus::QUARANTINED === $record->media_status
				)
			)
			->willReturnCallback(
				static fn( NewFinderReportMediaRecord $record ): FinderReportMediaRecord =>
					new FinderReportMediaRecord( 20, $record )
			);
		$storage = $this->createMock( PrivateMediaStorage::class );
		$storage->expects( self::once() )
			->method( 'put' )
			->with( PrivateMediaObjectKind::SOURCE, 'source-bytes' )
			->willReturn( $this->object( 'source-bytes', 'source-reference' ) );
		$scheduler = $this->createMock( FinderReportProcessingScheduler::class );
		$scheduler->expects( self::once() )->method( 'schedule' )->with( 10 );
		$messages = $this->createMock( FinderReportMessageProtector::class );
		$messages->expects( self::once() )
			->method( 'encrypt' )
			->with( 'I found this near the gate.', self::isInstanceOf( TagId::class ) )
			->willReturn( FinderReportMessageCiphertext::from_encrypted_bytes( 'encrypted-message' ) );
		$events   = new InMemoryEventRepository();
		$use_case = new SubmitFinderReport(
			$this->tag_reader(),
			$this->enabled_features(),
			$this->allowing_limiter(),
			$this->source_inspector(),
			$storage,
			$messages,
			$reports,
			$media,
			$events,
			new ImmediateTransactionManager(),
			$scheduler,
			new FixedClock( $this->now )
		);

		$result = $use_case->execute( $this->submission_input( 'I found this near the gate.' ) );

		self::assertSame( 10, $result->finder_report_id );
		self::assertCount( 1, $events->records );
		self::assertSame( 'finder_report_submitted', $events->records[0]->data->event_type );
		self::assertNull( $events->records[0]->data->metadata->json() );
	}

	/** Disabled controls stop intake before inspection, storage, or persistence. */
	public function test_submission_fails_before_private_writes_when_disabled(): void {
		$features = $this->createMock( FeatureFlagReader::class );
		$features->method( 'is_enabled' )->willReturn( false );
		$inspector = $this->createMock( FinderEvidenceSourceInspector::class );
		$inspector->expects( self::never() )->method( 'inspect' );
		$storage = $this->createMock( PrivateMediaStorage::class );
		$storage->expects( self::never() )->method( 'put' );

		$use_case = new SubmitFinderReport(
			$this->tag_reader(),
			$features,
			$this->allowing_limiter(),
			$inspector,
			$storage,
			$this->createMock( FinderReportMessageProtector::class ),
			$this->createMock( FinderReportRepository::class ),
			$this->createMock( FinderReportMediaRepository::class ),
			new InMemoryEventRepository(),
			new ImmediateTransactionManager(),
			$this->createMock( FinderReportProcessingScheduler::class ),
			new FixedClock( $this->now )
		);

		$this->expectException( FinderReportSubmissionException::class );
		$use_case->execute( $this->submission_input( '' ) );
	}

	/** Technical processing stores both controlled derivatives and reaches ready. */
	public function test_processing_converges_ready_without_content_review(): void {
		$reports = $this->processing_reports( true );
		$media   = $this->processing_media( true );
		$storage = $this->processing_storage();
		$events  = new InMemoryEventRepository();
		$process = new ProcessFinderReportEvidence(
			$reports,
			$media,
			$storage,
			$this->processor(),
			$events,
			new ImmediateTransactionManager(),
			new FixedClock( $this->now )
		);

		$process->execute( 10 );

		self::assertCount( 1, $events->records );
		self::assertSame( 'finder_report_evidence_ready', $events->records[0]->data->event_type );
		self::assertSame( 'processed', $events->records[0]->data->event_result );
	}

	/** A technical processing failure blocks the report without storing derivatives. */
	public function test_processing_fails_closed_when_image_processing_fails(): void {
		$reports = $this->processing_reports( false );
		$media   = $this->processing_media( false );
		$storage = $this->createMock( PrivateMediaStorage::class );
		$storage->expects( self::once() )
			->method( 'read' )
			->willReturn( 'source-bytes' );
		$storage->expects( self::never() )->method( 'put' );
		$processor = $this->createMock( FinderEvidenceImageProcessor::class );
		$processor->method( 'process' )->willThrowException( new RuntimeException( 'Decode failed.' ) );
		$events  = new InMemoryEventRepository();
		$process = new ProcessFinderReportEvidence(
			$reports,
			$media,
			$storage,
			$processor,
			$events,
			new ImmediateTransactionManager(),
			new FixedClock( $this->now )
		);

		try {
			$process->execute( 10 );
			self::fail( 'Technical processing failure must fail closed.' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'Finder evidence processing failed.', $exception->getMessage() );
		}

		self::assertCount( 1, $events->records );
		self::assertSame( 'finder_report_blocked', $events->records[0]->data->event_type );
	}

	/** Retention deletes all object kinds before expiring usable references. */
	public function test_cleanup_deletes_objects_and_preserves_audit_record(): void {
		$record = $this->media_record( FinderEvidenceStatus::READY, true );
		$media  = $this->createMock( FinderReportMediaRepository::class );
		$media->expects( self::once() )->method( 'find_expired' )->with( $this->now, 50 )->willReturn( array( $record ) );
		$media->expects( self::once() )->method( 'mark_deleted' )->with( 10, $this->now )->willReturn( true );
		$reports = $this->createMock( FinderReportRepository::class );
		$reports->expects( self::once() )->method( 'mark_expired' )->with( 10, $this->now )->willReturn( true );
		$deleted = array();
		$storage = $this->createMock( PrivateMediaStorage::class );
		$storage->expects( self::exactly( 3 ) )
			->method( 'delete' )
			->willReturnCallback(
				static function ( PrivateMediaObjectKind $kind, PrivateMediaObject $media_object ) use ( &$deleted ): void {
					unset( $media_object );
					$deleted[] = $kind;
				}
			);
		$events  = new InMemoryEventRepository();
		$cleanup = new CleanupFinderReportEvidence(
			$media,
			$reports,
			$storage,
			$events,
			new ImmediateTransactionManager(),
			new FixedClock( $this->now )
		);

		self::assertSame( 1, $cleanup->execute() );
		self::assertSame(
			array( PrivateMediaObjectKind::SOURCE, PrivateMediaObjectKind::REVIEW, PrivateMediaObjectKind::EMAIL ),
			$deleted
		);
		self::assertSame( 'finder_report_expired', $events->records[0]->data->event_type );
		self::assertNull( $events->records[0]->data->metadata->json() );
	}

	/** Cleanup cannot audit expiry when usable-reference convergence fails. */
	public function test_cleanup_fails_without_audit_when_state_transition_is_lost(): void {
		$media = $this->createMock( FinderReportMediaRepository::class );
		$media->method( 'find_expired' )->willReturn( array( $this->media_record() ) );
		$media->expects( self::once() )->method( 'mark_deleted' )->willReturn( false );
		$reports = $this->createMock( FinderReportRepository::class );
		$reports->expects( self::never() )->method( 'mark_expired' );
		$storage = $this->createMock( PrivateMediaStorage::class );
		$storage->expects( self::once() )->method( 'delete' );
		$events  = new InMemoryEventRepository();
		$cleanup = new CleanupFinderReportEvidence(
			$media,
			$reports,
			$storage,
			$events,
			new ImmediateTransactionManager(),
			new FixedClock( $this->now )
		);

		try {
			$cleanup->execute();
			self::fail( 'Lost cleanup transition must fail closed.' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'Finder evidence cleanup state transition failed.', $exception->getMessage() );
		}

		self::assertSame( array(), $events->records );
	}

	/** Build one eligible active Tag projection. */
	private function tag_reader(): PublicTagStateReader {
		$reader = $this->createMock( PublicTagStateReader::class );
		$reader->method( 'find' )->willReturn(
			new PublicTagStateRecord(
				42,
				TagType::CLASSIC_TAG,
				'Travel bag',
				TagStatus::ACTIVE,
				false,
				null,
				$this->now,
				BatchStatus::RELEASED,
				true
			)
		);

		return $reader;
	}

	/** Return operational controls enabled for both required flags. */
	private function enabled_features(): FeatureFlagReader {
		$features = $this->createMock( FeatureFlagReader::class );
		$features->method( 'is_enabled' )->willReturn( true );

		return $features;
	}

	/** Return an atomic limiter that accepts one test report. */
	private function allowing_limiter(): FinderReportRateLimiter {
		$limiter = $this->createMock( FinderReportRateLimiter::class );
		$limiter->method( 'reserve' )->willReturn( true );

		return $limiter;
	}

	/** Return deterministic source facts. */
	private function source_inspector(): FinderEvidenceSourceInspector {
		$inspector = $this->createMock( FinderEvidenceSourceInspector::class );
		$inspector->method( 'inspect' )->willReturn(
			new FinderEvidenceSourceMetadata(
				FinderEvidenceMime::JPEG,
				strlen( 'source-bytes' ),
				100,
				100,
				MediaDigest::from_digest( hash( 'sha256', 'source-bytes' ) )
			)
		);

		return $inspector;
	}

	/**
	 * Build one bounded anonymous submission.
	 *
	 * @param string $message Optional Finder message.
	 */
	private function submission_input( string $message ): FinderReportSubmissionInput {
		return new FinderReportSubmissionInput(
			TagId::from_canonical( 'A7R2W9' ),
			$message,
			new FinderEvidenceSource( 'source-bytes' ),
			LookupDigest::from_digest( str_repeat( 'a', 64 ) ),
			LookupDigest::from_digest( str_repeat( 'b', 64 ) )
		);
	}

	/**
	 * Build a processing Repository with exact state expectations.
	 *
	 * @param bool $ready Whether processing should converge to ready.
	 */
	private function processing_reports( bool $ready ): FinderReportRepository {
		$reports = $this->createMock( FinderReportRepository::class );
		$reports->method( 'find_by_id' )->with( 10 )->willReturn( $this->report_record() );
		$reports->expects( self::once() )->method( 'claim_processing' )->willReturn( true );

		if ( $ready ) {
			$reports->expects( self::once() )->method( 'mark_ready' )->with( 10, $this->now )->willReturn( true );
			$reports->expects( self::never() )->method( 'mark_blocked' );
		} else {
			$reports->expects( self::never() )->method( 'mark_ready' );
			$reports->expects( self::once() )->method( 'mark_blocked' )->with( 10, $this->now )->willReturn( true );
		}

		return $reports;
	}

	/**
	 * Build a media Repository with ready or rejected convergence.
	 *
	 * @param bool $ready Whether processing should converge to ready.
	 */
	private function processing_media( bool $ready ): FinderReportMediaRepository {
		$media = $this->createMock( FinderReportMediaRepository::class );
		$media->method( 'find_by_report_id' )->with( 10 )->willReturn( $this->media_record() );
		$media->expects( self::once() )->method( 'claim_processing' )->willReturn( true );

		if ( $ready ) {
			$media->expects( self::once() )
				->method( 'mark_ready' )
				->with( 10, self::isInstanceOf( MediaDerivative::class ), self::isInstanceOf( MediaDerivative::class ), $this->now )
				->willReturn( true );
			$media->expects( self::never() )->method( 'mark_rejected' );
		} else {
			$media->expects( self::never() )->method( 'mark_ready' );
			$media->expects( self::once() )->method( 'mark_rejected' )->willReturn( true );
		}

		return $media;
	}

	/** Build deterministic private storage for one successful processing run. */
	private function processing_storage(): PrivateMediaStorage {
		$storage = $this->createMock( PrivateMediaStorage::class );
		$storage->expects( self::once() )->method( 'read' )->willReturn( 'source-bytes' );
		$storage->expects( self::exactly( 2 ) )
			->method( 'put' )
			->willReturnCallback(
				fn( PrivateMediaObjectKind $kind, string $bytes ): PrivateMediaObject => $this->object(
					$bytes,
					$kind->value . '-reference'
				)
			);

		return $storage;
	}

	/** Return a processor whose source facts exactly match persistence. */
	private function processor(): FinderEvidenceImageProcessor {
		$processor = $this->createMock( FinderEvidenceImageProcessor::class );
		$processor->method( 'process' )->willReturn(
			new ProcessedFinderEvidence(
				FinderEvidenceMime::JPEG,
				strlen( 'source-bytes' ),
				100,
				100,
				MediaDigest::from_digest( hash( 'sha256', 'source-bytes' ) ),
				FinderEvidenceDerivative::review( 'review-bytes', 100, 100 ),
				FinderEvidenceDerivative::email( 'email-bytes', 100, 100 )
			)
		);

		return $processor;
	}

	/** Build one received report row. */
	private function report_record(): FinderReportRecord {
		return new FinderReportRecord(
			10,
			new NewFinderReportRecord(
				'A7R2W9',
				42,
				null,
				FinderReportStatus::RECEIVED,
				FinderEvidenceStatus::QUARANTINED,
				null,
				null,
				$this->now->modify( '+24 hours' ),
				$this->now,
				$this->now
			)
		);
	}

	/**
	 * Build one private-media row with optional controlled derivatives.
	 *
	 * @param FinderEvidenceStatus $status           Persisted evidence status.
	 * @param bool                 $with_derivatives Whether controlled derivatives exist.
	 */
	private function media_record(
		FinderEvidenceStatus $status = FinderEvidenceStatus::QUARANTINED,
		bool $with_derivatives = false
	): FinderReportMediaRecord {
		$review = $with_derivatives
			? MediaDerivative::review(
				$this->object( 'review-bytes', 'review-reference' )->reference_ciphertext,
				MediaDigest::from_digest( hash( 'sha256', 'review-bytes' ) ),
				strlen( 'review-bytes' ),
				100,
				100
			)
			: null;
		$email  = $with_derivatives
			? MediaDerivative::email(
				$this->object( 'email-bytes', 'email-reference' )->reference_ciphertext,
				MediaDigest::from_digest( hash( 'sha256', 'email-bytes' ) ),
				strlen( 'email-bytes' ),
				100,
				100
			)
			: null;

		return new FinderReportMediaRecord(
			20,
			new NewFinderReportMediaRecord(
				10,
				$this->object( 'source-bytes', 'source-reference' )->reference_ciphertext,
				'v1',
				MediaDigest::from_digest( hash( 'sha256', 'source-bytes' ) ),
				FinderEvidenceMime::JPEG,
				strlen( 'source-bytes' ),
				100,
				100,
				$review,
				$email,
				$status,
				$this->now,
				$this->now->modify( '-24 hours' ),
				$this->now
			)
		);
	}

	/**
	 * Build one opaque private object descriptor.
	 *
	 * @param string $bytes     Object bytes used only for deterministic metadata.
	 * @param string $reference Opaque encrypted-reference fixture bytes.
	 */
	private function object( string $bytes, string $reference ): PrivateMediaObject {
		return new PrivateMediaObject(
			PrivateMediaReferenceCiphertext::from_encrypted_bytes( $reference ),
			'v1',
			MediaDigest::from_digest( hash( 'sha256', $bytes ) ),
			strlen( $bytes )
		);
	}
}
