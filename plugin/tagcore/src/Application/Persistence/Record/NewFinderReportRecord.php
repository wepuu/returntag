<?php
/**
 * New one-way Finder Report persistence data.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Value\FinderReportMessageCiphertext;
use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderReportStatus;

/**
 * Immutable privacy-preserving Finder Report row data.
 */
final readonly class NewFinderReportRecord {
	/**
	 * Create Finder Report persistence data.
	 *
	 * @param string                             $tag_id Canonical public Tag ID.
	 * @param int                                $owner_id_at_submission Submission-time Owner snapshot.
	 * @param FinderReportMessageCiphertext|null $message_ciphertext Optional encrypted message.
	 * @param FinderReportStatus                 $report_status Finder Report state.
	 * @param FinderEvidenceStatus               $evidence_status Evidence-processing state.
	 * @param DeliveryStatus|null                $owner_notification_status Optional delivery state.
	 * @param DateTimeImmutable|null             $owner_notified_at Optional Owner notification time.
	 * @param DateTimeImmutable                  $expires_at UTC expiry.
	 * @param DateTimeImmutable                  $created_at UTC creation time.
	 * @param DateTimeImmutable                  $updated_at UTC update time.
	 */
	public function __construct(
		public string $tag_id,
		public int $owner_id_at_submission,
		public ?FinderReportMessageCiphertext $message_ciphertext,
		public FinderReportStatus $report_status,
		public FinderEvidenceStatus $evidence_status,
		public ?DeliveryStatus $owner_notification_status,
		public ?DateTimeImmutable $owner_notified_at,
		public DateTimeImmutable $expires_at,
		public DateTimeImmutable $created_at,
		public DateTimeImmutable $updated_at
	) {
		RecordValidator::tag_id( $this->tag_id );
		RecordValidator::positive_id( $this->owner_id_at_submission, 'owner_id_at_submission' );
		RecordValidator::nullable_utc( $this->owner_notified_at, 'owner_notified_at' );
		RecordValidator::utc( $this->expires_at, 'expires_at' );
		RecordValidator::utc( $this->created_at, 'created_at' );
		RecordValidator::utc( $this->updated_at, 'updated_at' );
	}
}
