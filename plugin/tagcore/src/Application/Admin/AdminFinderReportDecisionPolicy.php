<?php
/**
 * Pure RT-328 Finder Report decision policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

use DateInterval;
use DateTimeImmutable;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderReportStatus;

/** Owns eligibility and the fixed Hold duration. */
final class AdminFinderReportDecisionPolicy {
	/**
	 * Decide a transition, or return null when it is unavailable.
	 *
	 * @param AdminFinderReportAction $action Requested action.
	 * @param AdminFinderReportState  $before Current state.
	 * @param DateTimeImmutable       $now Current UTC instant.
	 */
	public function decide( AdminFinderReportAction $action, AdminFinderReportState $before, DateTimeImmutable $now ): ?AdminFinderReportState {
		$eligible = in_array( $before->report_status, array( FinderReportStatus::READY, FinderReportStatus::NOTIFIED ), true )
			&& FinderEvidenceStatus::READY === $before->evidence_status
			&& $before->has_review_evidence
			&& $before->report_expires_at > $now
			&& $before->retention_until > $now;
		if ( ! $eligible ) {
			return null;
		}

		$active = $before->hold_active( $now );
		if ( AdminFinderReportAction::PLACE_HOLD === $action ) {
			return $active ? null : new AdminFinderReportState( $before->report_status, $before->evidence_status, $before->notification_status, $before->conversation_id, $before->report_expires_at, $before->retention_until, $now->add( new DateInterval( 'P90D' ) ), true );
		}
		if ( in_array( $action, array( AdminFinderReportAction::RELEASE_HOLD, AdminFinderReportAction::RESOLVE_NO_ACTION ), true ) ) {
			return ! $active ? null : new AdminFinderReportState( $before->report_status, $before->evidence_status, $before->notification_status, $before->conversation_id, $before->report_expires_at, $before->retention_until, null, true );
		}

		$notification = in_array( $before->notification_status, array( 'queued', 'deferred' ), true ) ? 'failed' : $before->notification_status;
		return new AdminFinderReportState( FinderReportStatus::BLOCKED, $before->evidence_status, $notification, $before->conversation_id, $before->report_expires_at, $before->retention_until, $now->add( new DateInterval( 'P90D' ) ), true );
	}
}
