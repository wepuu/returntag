<?php
/**
 * Notify the current Owner about one approved Finder Report.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use DateInterval;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportMediaRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderReportStatus;
use ReturnTag\TagCore\Domain\FinderReport\PrivateMediaObjectKind;
use ReturnTag\TagCore\Domain\Tag\TagId;
use Throwable;

/** Claims, resolves, sends, and converges one idempotent Owner alert. */
final readonly class NotifyFinderReportOwner {
	private const NOTIFIED_RETENTION = 'P30D';

	/**
	 * Create the notification use case.
	 *
	 * @param FeatureFlagReader                   $feature_flags Runtime controls.
	 * @param FinderReportRepository              $reports Report persistence.
	 * @param FinderReportMediaRepository         $media Media persistence.
	 * @param PrivateMediaStorage                 $storage Encrypted private storage.
	 * @param FinderReportMessageProtector        $messages Optional-message protection.
	 * @param FinderReportOwnerRecipientResolver  $recipients Current Owner resolver.
	 * @param FinderReportOwnerNotificationSender $sender Transactional email adapter.
	 * @param EventRepository                     $events Audit Events.
	 * @param TransactionManager                  $transactions Atomic database boundary.
	 * @param Clock                               $clock UTC clock.
	 */
	public function __construct(
		private FeatureFlagReader $feature_flags,
		private FinderReportRepository $reports,
		private FinderReportMediaRepository $media,
		private PrivateMediaStorage $storage,
		private FinderReportMessageProtector $messages,
		private FinderReportOwnerRecipientResolver $recipients,
		private FinderReportOwnerNotificationSender $sender,
		private EventRepository $events,
		private TransactionManager $transactions,
		private Clock $clock
	) {
	}

	/**
	 * Notify one current Owner without exposing report or Tag identifiers.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 */
	public function execute( int $finder_report_id ): FinderReportOwnerNotificationResult {
		if (
			$finder_report_id < 1
			|| ! $this->feature_flags->is_enabled( FeatureFlag::FINDER_CONTACT )
			|| ! $this->feature_flags->is_enabled( FeatureFlag::FINDER_EVIDENCE )
			|| ! $this->feature_flags->is_enabled( FeatureFlag::EMAIL_DISPATCH )
		) {
			return FinderReportOwnerNotificationResult::NO_ACTION;
		}

		$report = $this->reports->find_by_id( $finder_report_id );
		$media  = $this->media->find_by_report_id( $finder_report_id );

		if (
			null === $report
			|| null === $media
			|| FinderReportStatus::READY !== $report->data->report_status
			|| FinderEvidenceStatus::READY !== $report->data->evidence_status
			|| FinderEvidenceStatus::READY !== $media->data->media_status
			|| null === $media->data->email_derivative
		) {
			return FinderReportOwnerNotificationResult::NO_ACTION;
		}

		$now = $this->clock->now();

		if ( ! $this->reports->claim_owner_notification( $finder_report_id, $now ) ) {
			return FinderReportOwnerNotificationResult::NO_ACTION;
		}

		$message        = null;
		$evidence_bytes = '';

		try {
			$tag_id    = TagId::from_canonical( $report->data->tag_id );
			$recipient = $this->recipients->resolve( $tag_id );

			if ( null === $recipient ) {
				return $this->fail( $finder_report_id, $now );
			}

			if ( null !== $report->data->message_ciphertext ) {
				$message = $this->messages->decrypt( $report->data->message_ciphertext, $tag_id );
			}

			$email_derivative = $media->data->email_derivative;
			$evidence_bytes   = $this->storage->read(
				PrivateMediaObjectKind::EMAIL,
				new PrivateMediaObject(
					$email_derivative->reference_ciphertext,
					$media->data->encryption_key_id,
					$email_derivative->sha256,
					$email_derivative->byte_count
				)
			);
			$idempotency_key  = hash( 'sha256', "returntag:finder-owner-notification:v1\0" . $finder_report_id . ':' . $email_derivative->sha256->value );
			$accepted         = $this->sender->send(
				new FinderReportOwnerNotificationEmail(
					$recipient->email,
					$message,
					$evidence_bytes,
					$idempotency_key
				)
			);

			if ( ! $accepted ) {
				return $this->fail( $finder_report_id, $now );
			}

			$retention = $now->add( new DateInterval( self::NOTIFIED_RETENTION ) );
			$this->transactions->transactional(
				function () use ( $finder_report_id, $retention, $now ): void {
					if (
						! $this->reports->mark_owner_notified( $finder_report_id, $retention, $now )
						|| ! $this->media->extend_notified_retention( $finder_report_id, $retention, $now )
					) {
						throw new \RuntimeException( 'Finder Report notification transition failed.' );
					}

					$this->append_event( 'finder_report_owner_notified', $finder_report_id, 'sent', $now );
				}
			);

			return FinderReportOwnerNotificationResult::SENT;
		} catch ( Throwable ) {
			return $this->fail( $finder_report_id, $now );
		} finally {
			if ( function_exists( 'sodium_memzero' ) ) {
				if ( is_string( $message ) && '' !== $message ) {
					sodium_memzero( $message );
				}

				if ( '' !== $evidence_bytes ) {
					sodium_memzero( $evidence_bytes );
				}
			}
		}
	}

	/**
	 * Mark a claimed notification terminally failed without throwing.
	 *
	 * @param int                $finder_report_id Internal report identifier.
	 * @param \DateTimeImmutable $now Current UTC time.
	 */
	private function fail( int $finder_report_id, \DateTimeImmutable $now ): FinderReportOwnerNotificationResult {
		try {
			$this->transactions->transactional(
				function () use ( $finder_report_id, $now ): void {
					if ( $this->reports->mark_owner_notification_failed( $finder_report_id, $now ) ) {
						$this->append_event( 'finder_report_owner_notification_failed', $finder_report_id, 'failed', $now );
					}
				}
			);
		} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Recovery marks stale claims failed.
			// Stale-claim recovery owns any failed convergence.
		}

		return FinderReportOwnerNotificationResult::FAILED;
	}

	/**
	 * Append a metadata-free notification Event.
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
