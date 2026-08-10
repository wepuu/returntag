<?php
/**
 * Finder Report Owner notification queue port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

/** Queues only one internal Finder Report identifier. */
interface FinderReportOwnerNotificationScheduler {
	/**
	 * Queue one report for notification.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 */
	public function schedule( int $finder_report_id ): void;

	/** Report whether the durable queue is available. */
	public function is_available(): bool;
}
