<?php
/**
 * Finder Report administrative decision use case.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;

/** Enforces the kill switch and exact confirmation before persistence. */
final readonly class ManageAdminFinderReportDecision {
	/**
	 * Create the use case.
	 *
	 * @param AdminFinderReportDecisionStore $store Atomic persistence port.
	 * @param FeatureFlagReader              $flags Operational controls.
	 * @param Clock                          $clock UTC clock.
	 */
	public function __construct( private AdminFinderReportDecisionStore $store, private FeatureFlagReader $flags, private Clock $clock ) {
	}

	/**
	 * Execute one authorized decision.
	 *
	 * @param int                     $report_id Finder Report identifier.
	 * @param AdminFinderReportAction $action Requested action.
	 * @param string                  $confirmation Exact numeric confirmation.
	 * @param AdminFinderReportState  $expected Submitted state snapshot.
	 * @param int                     $operator_id Operator User ID.
	 */
	public function execute( int $report_id, AdminFinderReportAction $action, string $confirmation, AdminFinderReportState $expected, int $operator_id ): AdminFinderReportDecisionResult {
		if ( $report_id < 1 || $operator_id < 1 || $confirmation !== (string) $report_id || ! $this->flags->is_enabled( FeatureFlag::ADMIN_FINDER_REPORT_DECISIONS ) ) {
			return AdminFinderReportDecisionResult::unavailable();
		}
		return $this->store->change( $report_id, $action, $expected, $operator_id, $this->clock->now() );
	}
}
