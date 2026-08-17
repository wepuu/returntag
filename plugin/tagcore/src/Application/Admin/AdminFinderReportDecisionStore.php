<?php
/**
 * Atomic Finder Report decision persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

use DateTimeImmutable;

/** Persists one decision and its audit Event atomically. */
interface AdminFinderReportDecisionStore {
	/**
	 * Apply one conditional decision.
	 *
	 * @param int                     $report_id Finder Report identifier.
	 * @param AdminFinderReportAction $action Requested action.
	 * @param AdminFinderReportState  $expected Submitted state snapshot.
	 * @param int                     $operator_id Operator User ID.
	 * @param DateTimeImmutable       $now Current UTC instant.
	 */
	public function change( int $report_id, AdminFinderReportAction $action, AdminFinderReportState $expected, int $operator_id, DateTimeImmutable $now ): AdminFinderReportDecisionResult;
}
