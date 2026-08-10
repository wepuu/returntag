<?php
/**
 * Finder Report private-media persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Repository;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Record\FinderReportMediaRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewFinderReportMediaRecord;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDerivative;

/**
 * Narrow private-media metadata persistence contract.
 */
interface FinderReportMediaRepository {
	/**
	 * Insert one private-media record.
	 *
	 * @param NewFinderReportMediaRecord $record New media data.
	 */
	public function insert( NewFinderReportMediaRecord $record ): FinderReportMediaRecord;

	/**
	 * Find the unique media record for one Finder Report.
	 *
	 * @param int $finder_report_id Parent report identifier.
	 */
	public function find_by_report_id( int $finder_report_id ): ?FinderReportMediaRecord;

	/**
	 * Claim quarantined or stale processing evidence.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param DateTimeImmutable $stale_before Stale lease cutoff.
	 */
	public function claim_processing( int $finder_report_id, DateTimeImmutable $now, DateTimeImmutable $stale_before ): bool;

	/**
	 * Persist approved derivatives and mark evidence ready.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param MediaDerivative   $review Private review derivative.
	 * @param MediaDerivative   $email Private email derivative.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_ready(
		int $finder_report_id,
		MediaDerivative $review,
		MediaDerivative $email,
		DateTimeImmutable $now
	): bool;

	/**
	 * Mark processing evidence rejected with a bounded retention deadline.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $retention_until Deletion deadline.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_rejected( int $finder_report_id, DateTimeImmutable $retention_until, DateTimeImmutable $now ): bool;

	/**
	 * Extend ready evidence retention after Owner notification.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $retention_until Notified evidence expiry.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function extend_notified_retention( int $finder_report_id, DateTimeImmutable $retention_until, DateTimeImmutable $now ): bool;

	/**
	 * Return a bounded expiry batch ordered by retention.
	 *
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param int               $limit Bounded row limit.
	 * @return list<FinderReportMediaRecord>
	 */
	public function find_expired( DateTimeImmutable $now, int $limit ): array;

	/**
	 * Return report IDs whose queued processing is absent or stale.
	 *
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param DateTimeImmutable $stale_before Stale lease cutoff.
	 * @param int               $limit Bounded row limit.
	 * @return list<int>
	 */
	public function find_processable( DateTimeImmutable $now, DateTimeImmutable $stale_before, int $limit ): array;

	/**
	 * Remove usable references and mark one evidence row deleted.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_deleted( int $finder_report_id, DateTimeImmutable $now ): bool;
}
