<?php
/**
 * Finder evidence queue port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

/** Schedules report-ID-only processing work. */
interface FinderReportProcessingScheduler {
	/** Confirm that the durable scheduling backend is ready for intake. */
	public function is_available(): bool;

	/**
	 * Schedule one report for immediate or delayed processing.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 * @param int $delay_seconds Non-negative delay.
	 */
	public function schedule( int $finder_report_id, int $delay_seconds = 0 ): void;
}
