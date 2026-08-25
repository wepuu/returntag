<?php
/**
 * Finder Report persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Repository;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Record\FinderReportRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewFinderReportRecord;

/**
 * Narrow one-way Finder Report persistence contract.
 */
interface FinderReportRepository {
	/**
	 * Insert one Finder Report.
	 *
	 * @param NewFinderReportRecord $record New report data.
	 */
	public function insert( NewFinderReportRecord $record ): FinderReportRecord;

	/**
	 * Find one Finder Report by internal identifier.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 */
	public function find_by_id( int $finder_report_id ): ?FinderReportRecord;

	/**
	 * Read the canonical Conversation link, when verified.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 */
	public function find_conversation_id( int $finder_report_id ): ?int;

	/**
	 * Resolve the current Owner at Conversation creation time.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 */
	public function find_current_owner_id( int $finder_report_id ): ?int;

	/**
	 * Atomically attach the first canonical Conversation to a report.
	 *
	 * @param int               $finder_report_id Internal report identifier.
	 * @param int               $conversation_id Internal Conversation identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function link_conversation( int $finder_report_id, int $conversation_id, DateTimeImmutable $now ): bool;

	/**
	 * Claim a received or stale-processing report for evidence work.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param DateTimeImmutable $stale_before Stale lease cutoff.
	 */
	public function claim_processing( int $finder_report_id, DateTimeImmutable $now, DateTimeImmutable $stale_before ): bool;

	/**
	 * Mark a processing report ready after controlled technical processing.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_ready( int $finder_report_id, DateTimeImmutable $now ): bool;

	/**
	 * Mark a non-terminal report blocked.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_blocked( int $finder_report_id, DateTimeImmutable $now ): bool;

	/**
	 * Atomically claim one ready report for Owner notification.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function claim_owner_notification( int $finder_report_id, DateTimeImmutable $now ): bool;

	/**
	 * Mark one claimed notification accepted by the mailer.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $expires_at Notified evidence retention boundary.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_owner_notified( int $finder_report_id, DateTimeImmutable $expires_at, DateTimeImmutable $now ): bool;

	/**
	 * Mark one claimed notification terminally failed.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_owner_notification_failed( int $finder_report_id, DateTimeImmutable $now ): bool;

	/**
	 * Find a bounded batch of ready, unclaimed notifications.
	 *
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param int               $limit Bounded row limit.
	 * @return list<int>
	 */
	public function find_notifiable( DateTimeImmutable $now, int $limit ): array;

	/**
	 * Find a bounded batch of stale in-flight notification claims.
	 *
	 * @param DateTimeImmutable $stale_before Stale claim cutoff.
	 * @param int               $limit Bounded row limit.
	 * @return list<int>
	 */
	public function find_stale_owner_notification_claims( DateTimeImmutable $stale_before, int $limit ): array;

	/**
	 * Mark a retained terminal report expired.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_expired( int $finder_report_id, DateTimeImmutable $now ): bool;
}
