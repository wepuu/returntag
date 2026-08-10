<?php
/**
 * RT-315 Finder Report Owner notification tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\FinderReport\ConvergeStaleFinderReportNotifications;
use ReturnTag\TagCore\Application\FinderReport\FinderReportMessageProtector;
use ReturnTag\TagCore\Application\FinderReport\FinderReportOwnerNotificationEmail;
use ReturnTag\TagCore\Application\FinderReport\FinderReportOwnerNotificationResult;
use ReturnTag\TagCore\Application\FinderReport\FinderReportOwnerNotificationSender;
use ReturnTag\TagCore\Application\FinderReport\FinderReportOwnerRecipient;
use ReturnTag\TagCore\Application\FinderReport\FinderReportOwnerRecipientResolver;
use ReturnTag\TagCore\Application\FinderReport\NotifyFinderReportOwner;
use ReturnTag\TagCore\Application\FinderReport\PrivateMediaObject;
use ReturnTag\TagCore\Application\FinderReport\PrivateMediaStorage;
use ReturnTag\TagCore\Application\Persistence\Record\FinderReportMediaRecord;
use ReturnTag\TagCore\Application\Persistence\Record\FinderReportRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewFinderReportMediaRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewFinderReportRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportMediaRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportRepository;
use ReturnTag\TagCore\Application\Persistence\Value\FinderReportMessageCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDerivative;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDigest;
use ReturnTag\TagCore\Application\Persistence\Value\PrivateMediaReferenceCiphertext;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceMime;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderReportStatus;
use ReturnTag\TagCore\Domain\FinderReport\PrivateMediaObjectKind;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\ImmediateTransactionManager;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryEventRepository;

/** Verifies idempotent, current-Owner, privacy-minimized notification behavior. */
final class FinderReportOwnerNotificationTest extends TestCase {
	/**
	 * Fixed UTC time.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $now;

	/** Establish deterministic notification time. */
	protected function setUp(): void {
		$this->now = new DateTimeImmutable( '2026-08-10 03:00:00', new DateTimeZone( 'UTC' ) );
	}

	/** A ready report sends once to the current Owner and extends evidence retention. */
	public function test_ready_report_notifies_current_owner_once(): void {
		$reports = $this->ready_reports();
		$reports->expects( self::once() )->method( 'claim_owner_notification' )->with( 10, $this->now )->willReturn( true );
		$reports->expects( self::once() )
			->method( 'mark_owner_notified' )
			->with( 10, $this->now->modify( '+30 days' ), $this->now )
			->willReturn( true );
		$reports->expects( self::never() )->method( 'mark_owner_notification_failed' );

		$media = $this->ready_media();
		$media->expects( self::once() )
			->method( 'extend_notified_retention' )
			->with( 10, $this->now->modify( '+30 days' ), $this->now )
			->willReturn( true );

		$storage = $this->createMock( PrivateMediaStorage::class );
		$storage->expects( self::once() )
			->method( 'read' )
			->with( PrivateMediaObjectKind::EMAIL, self::isInstanceOf( PrivateMediaObject::class ) )
			->willReturn( $this->jpeg() );
		$messages = $this->createMock( FinderReportMessageProtector::class );
		$messages->expects( self::once() )
			->method( 'decrypt' )
			->with( self::isInstanceOf( FinderReportMessageCiphertext::class ), self::isInstanceOf( TagId::class ) )
			->willReturn( 'I found this beside the airport carousel.' );
		$recipients = $this->createMock( FinderReportOwnerRecipientResolver::class );
		$recipients->expects( self::once() )
			->method( 'resolve' )
			->with( self::callback( static fn( TagId $tag_id ): bool => 'A7R2W9' === $tag_id->value ) )
			->willReturn( new FinderReportOwnerRecipient( 84, new EmailAddress( 'current-owner@example.test' ) ) );
		$sender = $this->createMock( FinderReportOwnerNotificationSender::class );
		$sender->expects( self::once() )
			->method( 'send' )
			->with(
				self::callback(
					fn( FinderReportOwnerNotificationEmail $email ): bool => 'current-owner@example.test' === $email->recipient->value
						&& 'I found this beside the airport carousel.' === $email->message
						&& $this->jpeg() === $email->evidence_jpeg
						&& 1 === preg_match( '/^[a-f0-9]{64}$/D', $email->idempotency_key )
				)
			)
			->willReturn( true );
		$events = new InMemoryEventRepository();

		$result = $this->use_case( $reports, $media, $storage, $messages, $recipients, $sender, $events )->execute( 10 );

		self::assertSame( FinderReportOwnerNotificationResult::SENT, $result );
		self::assertCount( 1, $events->records );
		self::assertSame( 'finder_report_owner_notified', $events->records[0]->data->event_type );
		self::assertNull( $events->records[0]->data->metadata->json() );
	}

	/** Each independent operational control stops work before the report is claimed. */
	public function test_notification_requires_all_three_feature_flags(): void {
		foreach ( array( FeatureFlag::FINDER_CONTACT, FeatureFlag::FINDER_EVIDENCE, FeatureFlag::EMAIL_DISPATCH ) as $disabled ) {
			$features = $this->createMock( FeatureFlagReader::class );
			$features->method( 'is_enabled' )->willReturnCallback( static fn( FeatureFlag $flag ): bool => $flag !== $disabled );
			$reports = $this->createMock( FinderReportRepository::class );
			$reports->expects( self::never() )->method( 'find_by_id' );
			$sender = $this->createMock( FinderReportOwnerNotificationSender::class );
			$sender->expects( self::never() )->method( 'send' );

			$result = $this->use_case(
				$reports,
				$this->createMock( FinderReportMediaRepository::class ),
				$this->createMock( PrivateMediaStorage::class ),
				$this->createMock( FinderReportMessageProtector::class ),
				$this->createMock( FinderReportOwnerRecipientResolver::class ),
				$sender,
				new InMemoryEventRepository(),
				$features
			)->execute( 10 );

			self::assertSame( FinderReportOwnerNotificationResult::NO_ACTION, $result );
		}
	}

	/** An already claimed report is a no-op and cannot emit a duplicate email. */
	public function test_duplicate_worker_cannot_send_again(): void {
		$reports = $this->ready_reports();
		$reports->expects( self::once() )->method( 'claim_owner_notification' )->willReturn( false );
		$sender = $this->createMock( FinderReportOwnerNotificationSender::class );
		$sender->expects( self::never() )->method( 'send' );

		$result = $this->use_case(
			$reports,
			$this->ready_media(),
			$this->createMock( PrivateMediaStorage::class ),
			$this->createMock( FinderReportMessageProtector::class ),
			$this->createMock( FinderReportOwnerRecipientResolver::class ),
			$sender,
			new InMemoryEventRepository()
		)->execute( 10 );

		self::assertSame( FinderReportOwnerNotificationResult::NO_ACTION, $result );
	}

	/** Missing current ownership fails terminally without falling back to the snapshot. */
	public function test_missing_current_owner_fails_without_sending(): void {
		$reports = $this->ready_reports();
		$reports->method( 'claim_owner_notification' )->willReturn( true );
		$reports->expects( self::once() )->method( 'mark_owner_notification_failed' )->with( 10, $this->now )->willReturn( true );
		$recipients = $this->createMock( FinderReportOwnerRecipientResolver::class );
		$recipients->method( 'resolve' )->willReturn( null );
		$sender = $this->createMock( FinderReportOwnerNotificationSender::class );
		$sender->expects( self::never() )->method( 'send' );
		$events = new InMemoryEventRepository();

		$result = $this->use_case(
			$reports,
			$this->ready_media(),
			$this->createMock( PrivateMediaStorage::class ),
			$this->createMock( FinderReportMessageProtector::class ),
			$recipients,
			$sender,
			$events
		)->execute( 10 );

		self::assertSame( FinderReportOwnerNotificationResult::FAILED, $result );
		self::assertSame( 'finder_report_owner_notification_failed', $events->records[0]->data->event_type );
	}

	/** Mailer rejection becomes a bounded terminal failure rather than an automatic retry loop. */
	public function test_mailer_rejection_is_terminal(): void {
		$reports = $this->ready_reports();
		$reports->method( 'claim_owner_notification' )->willReturn( true );
		$reports->expects( self::once() )->method( 'mark_owner_notification_failed' )->willReturn( true );
		$reports->expects( self::never() )->method( 'mark_owner_notified' );
		$media = $this->ready_media();
		$media->expects( self::never() )->method( 'extend_notified_retention' );
		$storage = $this->createMock( PrivateMediaStorage::class );
		$storage->method( 'read' )->willReturn( $this->jpeg() );
		$messages = $this->createMock( FinderReportMessageProtector::class );
		$messages->method( 'decrypt' )->willReturn( 'I found this beside the airport carousel.' );
		$recipients = $this->createMock( FinderReportOwnerRecipientResolver::class );
		$recipients->method( 'resolve' )->willReturn( new FinderReportOwnerRecipient( 84, new EmailAddress( 'owner@example.test' ) ) );
		$sender = $this->createMock( FinderReportOwnerNotificationSender::class );
		$sender->method( 'send' )->willReturn( false );

		$result = $this->use_case( $reports, $media, $storage, $messages, $recipients, $sender, new InMemoryEventRepository() )->execute( 10 );

		self::assertSame( FinderReportOwnerNotificationResult::FAILED, $result );
	}

	/** Stale ambiguous claims fail closed so recovery cannot resend them automatically. */
	public function test_stale_claim_convergence_is_bounded_and_audited(): void {
		$reports = $this->createMock( FinderReportRepository::class );
		$reports->expects( self::once() )
			->method( 'find_stale_owner_notification_claims' )
			->with( $this->now->modify( '-15 minutes' ), 100 )
			->willReturn( array( 10, 11 ) );
		$reports->expects( self::exactly( 2 ) )->method( 'mark_owner_notification_failed' )->willReturn( true, false );
		$events   = new InMemoryEventRepository();
		$converge = new ConvergeStaleFinderReportNotifications(
			$reports,
			$events,
			new ImmediateTransactionManager(),
			new FixedClock( $this->now )
		);

		self::assertSame( 1, $converge->execute( 999 ) );
		self::assertCount( 1, $events->records );
		self::assertSame( 'finder_report_owner_notification_failed', $events->records[0]->data->event_type );
	}

	/** The email DTO rejects non-JPEG evidence even when it is otherwise bounded. */
	public function test_email_contract_rejects_non_jpeg_bytes(): void {
		$this->expectException( \InvalidArgumentException::class );
		new FinderReportOwnerNotificationEmail(
			new EmailAddress( 'owner@example.test' ),
			null,
			'not-a-jpeg',
			str_repeat( 'a', 64 )
		);
	}

	/** Build a fully ready report repository. */
	private function ready_reports(): FinderReportRepository {
		$reports = $this->createMock( FinderReportRepository::class );
		$reports->method( 'find_by_id' )->with( 10 )->willReturn( $this->ready_report() );

		return $reports;
	}

	/** Build a fully ready media repository. */
	private function ready_media(): FinderReportMediaRepository {
		$media = $this->createMock( FinderReportMediaRepository::class );
		$media->method( 'find_by_report_id' )->with( 10 )->willReturn( $this->ready_media_record() );

		return $media;
	}

	/**
	 * Compose the notification use case with injectable boundaries.
	 *
	 * @param FinderReportRepository              $reports Report persistence.
	 * @param FinderReportMediaRepository         $media Media persistence.
	 * @param PrivateMediaStorage                 $storage Private object storage.
	 * @param FinderReportMessageProtector        $messages Optional-message protection.
	 * @param FinderReportOwnerRecipientResolver  $recipients Current-Owner resolution.
	 * @param FinderReportOwnerNotificationSender $sender Transactional mail adapter.
	 * @param InMemoryEventRepository             $events Captured audit Events.
	 * @param FeatureFlagReader|null              $features Optional runtime controls.
	 */
	private function use_case(
		FinderReportRepository $reports,
		FinderReportMediaRepository $media,
		PrivateMediaStorage $storage,
		FinderReportMessageProtector $messages,
		FinderReportOwnerRecipientResolver $recipients,
		FinderReportOwnerNotificationSender $sender,
		InMemoryEventRepository $events,
		?FeatureFlagReader $features = null
	): NotifyFinderReportOwner {
		return new NotifyFinderReportOwner(
			$features ?? $this->enabled_features(),
			$reports,
			$media,
			$storage,
			$messages,
			$recipients,
			$sender,
			$events,
			new ImmediateTransactionManager(),
			new FixedClock( $this->now )
		);
	}

	/** Enable all operational controls for a happy-path Worker. */
	private function enabled_features(): FeatureFlagReader {
		$features = $this->createMock( FeatureFlagReader::class );
		$features->method( 'is_enabled' )->willReturn( true );

		return $features;
	}

	/** Build a ready report with an encrypted optional message. */
	private function ready_report(): FinderReportRecord {
		return new FinderReportRecord(
			10,
			new NewFinderReportRecord(
				'A7R2W9',
				42,
				FinderReportMessageCiphertext::from_encrypted_bytes( 'encrypted-message' ),
				FinderReportStatus::READY,
				FinderEvidenceStatus::READY,
				null,
				null,
				$this->now->modify( '+24 hours' ),
				$this->now->modify( '-5 minutes' ),
				$this->now->modify( '-5 minutes' )
			)
		);
	}

	/** Build ready private media with the approved email derivative. */
	private function ready_media_record(): FinderReportMediaRecord {
		$jpeg = $this->jpeg();

		return new FinderReportMediaRecord(
			20,
			new NewFinderReportMediaRecord(
				10,
				PrivateMediaReferenceCiphertext::from_encrypted_bytes( 'source-reference' ),
				'v1',
				MediaDigest::from_digest( hash( 'sha256', 'source-bytes' ) ),
				FinderEvidenceMime::JPEG,
				strlen( 'source-bytes' ),
				100,
				100,
				MediaDerivative::review(
					PrivateMediaReferenceCiphertext::from_encrypted_bytes( 'review-reference' ),
					MediaDigest::from_digest( hash( 'sha256', 'review-bytes' ) ),
					strlen( 'review-bytes' ),
					100,
					100
				),
				MediaDerivative::email(
					PrivateMediaReferenceCiphertext::from_encrypted_bytes( 'email-reference' ),
					MediaDigest::from_digest( hash( 'sha256', $jpeg ) ),
					strlen( $jpeg ),
					100,
					100
				),
				FinderEvidenceStatus::READY,
				$this->now->modify( '-10 minutes' ),
				$this->now->modify( '+24 hours' ),
				$this->now->modify( '-5 minutes' )
			)
		);
	}

	/** Return one minimal valid JPEG byte sequence. */
	private function jpeg(): string {
		return "\xFF\xD8returntag-email-evidence\xFF\xD9";
	}
}
