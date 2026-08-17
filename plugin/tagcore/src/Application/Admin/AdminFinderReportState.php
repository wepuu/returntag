<?php
/**
 * Privacy-safe Finder Report review state.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

use DateTimeImmutable;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderReportStatus;

/** One submitted or committed review snapshot. */
final readonly class AdminFinderReportState {
	/**
	 * Create one state snapshot.
	 *
	 * @param FinderReportStatus     $report_status Canonical Report status.
	 * @param FinderEvidenceStatus   $evidence_status Canonical evidence status.
	 * @param string|null            $notification_status Nullable notification status.
	 * @param int|null               $conversation_id Internal Conversation identity, when linked.
	 * @param DateTimeImmutable      $report_expires_at Report expiry boundary.
	 * @param DateTimeImmutable      $retention_until Ordinary evidence retention boundary.
	 * @param DateTimeImmutable|null $hold_until Current Hold boundary.
	 * @param bool                   $has_review_evidence Whether the Review derivative exists.
	 */
	public function __construct(
		public FinderReportStatus $report_status,
		public FinderEvidenceStatus $evidence_status,
		public ?string $notification_status,
		public ?int $conversation_id,
		public DateTimeImmutable $report_expires_at,
		public DateTimeImmutable $retention_until,
		public ?DateTimeImmutable $hold_until,
		public bool $has_review_evidence
	) {
	}

	/**
	 * Determine whether the Hold is active at the supplied instant.
	 *
	 * @param DateTimeImmutable $now Current UTC instant.
	 */
	public function hold_active( DateTimeImmutable $now ): bool {
		return null !== $this->hold_until && $this->hold_until > $now;
	}
}
