<?php
/**
 * Process one quarantined Finder Report evidence image.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use DateInterval;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportMediaRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDerivative;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderReportStatus;
use ReturnTag\TagCore\Domain\FinderReport\PrivateMediaObjectKind;
use RuntimeException;
use Throwable;

/** Claims, processes, reviews, stores, and converges one evidence record. */
final readonly class ProcessFinderReportEvidence {
	/**
	 * Create the use case.
	 *
	 * @param FinderReportRepository       $reports Report persistence.
	 * @param FinderReportMediaRepository  $media Media persistence.
	 * @param PrivateMediaStorage          $storage Private storage.
	 * @param FinderEvidenceImageProcessor $processor Image processor.
	 * @param ReviewFinderEvidence         $review Safety review use case.
	 * @param EventRepository              $events Audit Events.
	 * @param TransactionManager           $transactions Atomic boundary.
	 * @param Clock                        $clock UTC clock.
	 */
	public function __construct(
		private FinderReportRepository $reports,
		private FinderReportMediaRepository $media,
		private PrivateMediaStorage $storage,
		private FinderEvidenceImageProcessor $processor,
		private ReviewFinderEvidence $review,
		private EventRepository $events,
		private TransactionManager $transactions,
		private Clock $clock
	) {
	}

	/**
	 * Process one internal report identifier idempotently.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 * @throws RuntimeException When processing fails closed.
	 */
	public function execute( int $finder_report_id ): void {
		$now    = $this->clock->now();
		$report = $this->reports->find_by_id( $finder_report_id );
		$media  = $this->media->find_by_report_id( $finder_report_id );

		if ( null === $report || null === $media ) {
			throw new RuntimeException( 'Finder evidence processing is unavailable.' );
		}

		if (
			in_array( $report->data->report_status, array( FinderReportStatus::READY, FinderReportStatus::NOTIFIED, FinderReportStatus::BLOCKED, FinderReportStatus::EXPIRED ), true )
			|| in_array( $media->data->media_status, array( FinderEvidenceStatus::READY, FinderEvidenceStatus::REJECTED, FinderEvidenceStatus::DELETED ), true )
		) {
			return;
		}

		$stale_before = $now->sub( new DateInterval( 'PT15M' ) );
		$claimed      = $this->transactions->transactional(
			function () use ( $finder_report_id, $now, $stale_before ): bool {
				$report_claimed = $this->reports->claim_processing( $finder_report_id, $now, $stale_before );
				$media_claimed  = $this->media->claim_processing( $finder_report_id, $now, $stale_before );

				if ( $report_claimed !== $media_claimed ) {
					throw new RuntimeException( 'Finder evidence state claim failed.' );
				}

				return $report_claimed;
			}
		);

		if ( ! $claimed ) {
			return;
		}

		$review_object = null;
		$email_object  = null;

		try {
			$source_object = new PrivateMediaObject(
				$media->data->object_reference_ciphertext,
				$media->data->encryption_key_id,
				$media->data->content_sha256,
				$media->data->source_byte_count
			);
			$bytes         = $this->storage->read( PrivateMediaObjectKind::SOURCE, $source_object );
			$processed     = $this->processor->process( new FinderEvidenceSource( $bytes ) );

			if (
				$processed->source_mime !== $media->data->source_mime
				|| $processed->source_byte_count !== $media->data->source_byte_count
				|| $processed->source_width !== $media->data->source_width
				|| $processed->source_height !== $media->data->source_height
				|| ! hash_equals( $processed->source_sha256->value, $media->data->content_sha256->value )
			) {
				throw new RuntimeException( 'Finder evidence processing is unavailable.' );
			}

			$approved      = $this->review->review( $processed );
			$review_object = $this->storage->put( PrivateMediaObjectKind::REVIEW, $approved->evidence->review->bytes );
			$email_object  = $this->storage->put( PrivateMediaObjectKind::EMAIL, $approved->evidence->email->bytes );
			$review_value  = MediaDerivative::review(
				$review_object->reference_ciphertext,
				$review_object->sha256,
				$review_object->byte_count,
				$approved->evidence->review->width,
				$approved->evidence->review->height
			);
			$email_value   = MediaDerivative::email(
				$email_object->reference_ciphertext,
				$email_object->sha256,
				$email_object->byte_count,
				$approved->evidence->email->width,
				$approved->evidence->email->height
			);

			$this->transactions->transactional(
				function () use ( $finder_report_id, $review_value, $email_value, $now ): void {
					if (
						! $this->media->mark_ready( $finder_report_id, $review_value, $email_value, $now )
						|| ! $this->reports->mark_ready( $finder_report_id, $now )
					) {
						throw new RuntimeException( 'Finder evidence state transition failed.' );
					}

					$this->append_event( 'finder_report_evidence_ready', $finder_report_id, 'approved', $now );
				}
			);
		} catch ( Throwable $exception ) {
			foreach ( array( array( PrivateMediaObjectKind::REVIEW, $review_object ), array( PrivateMediaObjectKind::EMAIL, $email_object ) ) as [ $kind, $object ] ) {
				if ( $object instanceof PrivateMediaObject ) {
					try {
						$this->storage->delete( $kind, $object );
					} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Retention worker retries durable cleanup.
						// The retention worker will retry any durable object cleanup.
					}
				}
			}

			$this->block( $finder_report_id, $now );

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Wrapped, never rendered.
			throw new RuntimeException( 'Finder evidence processing failed.', 0, $exception );
		}
	}

	/**
	 * Fail closed after any terminal processing or safety error.
	 *
	 * @param int                $finder_report_id Internal report identifier.
	 * @param \DateTimeImmutable $now Current UTC time.
	 */
	private function block( int $finder_report_id, \DateTimeImmutable $now ): void {
		$retention = $now->add( new DateInterval( 'PT24H' ) );

		$this->transactions->transactional(
			function () use ( $finder_report_id, $retention, $now ): void {
				if (
					! $this->media->mark_rejected( $finder_report_id, $retention, $now )
					|| ! $this->reports->mark_blocked( $finder_report_id, $now )
				) {
					throw new RuntimeException( 'Finder evidence blocked-state transition failed.' );
				}

				$this->append_event( 'finder_report_blocked', $finder_report_id, 'blocked', $now );
			}
		);
	}

	/**
	 * Append one metadata-free lifecycle Event.
	 *
	 * @param string             $type Event type.
	 * @param int                $finder_report_id Internal report identifier.
	 * @param string             $result Bounded result.
	 * @param \DateTimeImmutable $now Current UTC time.
	 */
	private function append_event( string $type, int $finder_report_id, string $result, \DateTimeImmutable $now ): void {
		$this->events->append(
			new NewEventRecord(
				$type,
				'system',
				null,
				'finder_report',
				(string) $finder_report_id,
				$result,
				null,
				EventMetadata::none(),
				$now
			)
		);
	}
}
