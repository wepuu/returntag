<?php
/**
 * Atomic WordPress database adapter for RT-328 decisions.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Admin\AdminFinderReportAction;
use ReturnTag\TagCore\Application\Admin\AdminFinderReportDecisionPolicy;
use ReturnTag\TagCore\Application\Admin\AdminFinderReportDecisionResult;
use ReturnTag\TagCore\Application\Admin\AdminFinderReportDecisionStore;
use ReturnTag\TagCore\Application\Admin\AdminFinderReportState;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderReportStatus;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ValueError;

/** Applies Hold, Block side effects, and audit in one transaction. */
final readonly class WpdbAdminFinderReportDecisionStore implements AdminFinderReportDecisionStore {
	/**
	 * Create the persistence adapter.
	 *
	 * @param WpdbGateway                     $db Prepared database gateway.
	 * @param TableNames                      $tables Trusted table names.
	 * @param DatabaseDateTimeCodec           $dates UTC database codec.
	 * @param TransactionManager              $transactions Transaction boundary.
	 * @param EventRepository                 $events Audit Event repository.
	 * @param AdminFinderReportDecisionPolicy $policy Pure transition policy.
	 */
	public function __construct(
		private WpdbGateway $db,
		private TableNames $tables,
		private DatabaseDateTimeCodec $dates,
		private TransactionManager $transactions,
		private EventRepository $events,
		private AdminFinderReportDecisionPolicy $policy
	) {}

	/**
	 * Execute one conditional, fully audited decision transaction.
	 *
	 * @param int                     $report_id Finder Report identifier.
	 * @param AdminFinderReportAction $action Requested action.
	 * @param AdminFinderReportState  $expected Submitted state snapshot.
	 * @param int                     $operator_id Operator User ID.
	 * @param DateTimeImmutable       $now Current UTC instant.
	 */
	public function change( int $report_id, AdminFinderReportAction $action, AdminFinderReportState $expected, int $operator_id, DateTimeImmutable $now ): AdminFinderReportDecisionResult {
		try {
			return $this->transactions->transactional(
				function () use ( $report_id, $action, $expected, $operator_id, $now ): AdminFinderReportDecisionResult {
					$row = $this->db->row(
						'SELECT r.report_status,r.evidence_status,r.owner_notification_status,r.conversation_id,r.expires_at,m.retention_until,m.hold_until,m.review_reference_ciphertext FROM %i r INNER JOIN %i m ON m.finder_report_id=r.finder_report_id WHERE r.finder_report_id=%d FOR UPDATE',
						array( $this->tables->finder_reports(), $this->tables->finder_report_media(), $report_id )
					);
					if ( null === $row ) {
						return AdminFinderReportDecisionResult::unavailable(); }
					try {
							$before = new AdminFinderReportState(
								FinderReportStatus::from( (string) $row['report_status'] ),
								FinderEvidenceStatus::from( (string) $row['evidence_status'] ),
								null === $row['owner_notification_status'] ? null : (string) $row['owner_notification_status'],
								null === $row['conversation_id'] ? null : (int) $row['conversation_id'],
								$this->dates->parse( (string) $row['expires_at'] ),
								$this->dates->parse( (string) $row['retention_until'] ),
								null === $row['hold_until'] ? null : $this->dates->parse( (string) $row['hold_until'] ),
								null !== $row['review_reference_ciphertext']
							);
					} catch ( ValueError ) {
						return AdminFinderReportDecisionResult::unavailable(); }
					if ( ! $this->same( $before, $expected ) ) {
						return AdminFinderReportDecisionResult::unavailable(); }
					$after = $this->policy->decide( $action, $before, $now );
					if ( null === $after ) {
						return AdminFinderReportDecisionResult::unavailable(); }

					if ( AdminFinderReportAction::BLOCK === $action ) {
						$this->require_one( $this->db->execute( 'UPDATE %i SET report_status=%s,updated_at=%s WHERE finder_report_id=%d AND report_status=%s AND evidence_status=%s', array( $this->tables->finder_reports(), 'blocked', $this->dates->format( $now ), $report_id, $before->report_status->value, $before->evidence_status->value ) ) );
						$this->db->execute( 'UPDATE %i SET owner_notification_status=%s,updated_at=%s WHERE finder_report_id=%d AND owner_notification_status IN (%s,%s)', array( $this->tables->finder_reports(), 'failed', $this->dates->format( $now ), $report_id, 'queued', 'deferred' ) );
					}

					$this->update_hold( $report_id, $before, $after, $operator_id, $now );
					if ( AdminFinderReportAction::BLOCK === $action && null !== $before->conversation_id ) {
						$this->revoke_conversation( $before->conversation_id, $now );
					}
					$event = match ( $action ) {
						AdminFinderReportAction::PLACE_HOLD => 'finder_evidence_hold_placed',
						AdminFinderReportAction::RELEASE_HOLD => 'finder_evidence_hold_released',
						AdminFinderReportAction::RESOLVE_NO_ACTION => 'finder_report_review_no_action',
						AdminFinderReportAction::BLOCK => 'finder_report_blocked',
					};
					$this->events->append( new NewEventRecord( $event, 'user', $operator_id, 'finder_report', (string) $report_id, 'success', null, EventMetadata::none(), $now ) );
					return AdminFinderReportDecisionResult::changed( $after );
				}
			);
		} catch ( AdminFinderReportDecisionConflict ) {
			return AdminFinderReportDecisionResult::unavailable();
		}
	}

	/**
	 * Compare the complete privacy-safe concurrency snapshot.
	 *
	 * @param AdminFinderReportState $a First state.
	 * @param AdminFinderReportState $b Second state.
	 */
	private function same( AdminFinderReportState $a, AdminFinderReportState $b ): bool {
		return $a->report_status === $b->report_status && $a->evidence_status === $b->evidence_status && $a->notification_status === $b->notification_status && ( null !== $a->conversation_id ) === ( null !== $b->conversation_id ) && $a->report_expires_at->getTimestamp() === $b->report_expires_at->getTimestamp() && $a->retention_until->getTimestamp() === $b->retention_until->getTimestamp() && $a->hold_until?->getTimestamp() === $b->hold_until?->getTimestamp() && $a->has_review_evidence === $b->has_review_evidence;
	}

	/**
	 * Conditionally place or clear the complete Hold tuple.
	 *
	 * @param int                    $report_id Finder Report identifier.
	 * @param AdminFinderReportState $before Current state.
	 * @param AdminFinderReportState $after Approved state.
	 * @param int                    $operator_id Operator User ID.
	 * @param DateTimeImmutable      $now Current UTC instant.
	 */
	private function update_hold( int $report_id, AdminFinderReportState $before, AdminFinderReportState $after, int $operator_id, DateTimeImmutable $now ): void {
		$where = null === $before->hold_until ? 'hold_until IS NULL' : 'hold_until=%s';
		$args  = array( $this->tables->finder_report_media() );
		if ( null === $after->hold_until ) {
			$sql    = 'UPDATE %i SET hold_until=NULL,hold_placed_at=NULL,hold_placed_by=NULL,updated_at=%s WHERE finder_report_id=%d AND ' . $where;
			$args[] = $this->dates->format( $now );
			$args[] = $report_id;
		} else {
			$sql = 'UPDATE %i SET hold_until=%s,hold_placed_at=%s,hold_placed_by=%d,updated_at=%s WHERE finder_report_id=%d AND media_status=%s AND ' . $where;
			array_push( $args, $this->dates->format( $after->hold_until ), $this->dates->format( $now ), $operator_id, $this->dates->format( $now ), $report_id, FinderEvidenceStatus::READY->value );
		}
		if ( null !== $before->hold_until ) {
			$args[] = $this->dates->format( $before->hold_until ); }
		$this->require_one( $this->db->execute( $sql, $args ) );
	}

	/**
	 * Revoke every current linked Conversation access path.
	 *
	 * @param int               $conversation_id Conversation identifier.
	 * @param DateTimeImmutable $now Current UTC instant.
	 */
	private function revoke_conversation( int $conversation_id, DateTimeImmutable $now ): void {
		$this->db->execute( 'UPDATE %i SET delivery_status=%s WHERE conversation_id=%d AND delivery_status IN (%s,%s)', array( $this->tables->messages(), 'failed', $conversation_id, 'queued', 'in_flight' ) );
		$this->db->execute( 'UPDATE %i SET conversation_status=%s,last_activity_at=%s WHERE conversation_id=%d AND conversation_status IN (%s,%s)', array( $this->tables->conversations(), 'blocked', $this->dates->format( $now ), $conversation_id, 'pending_verification', 'open' ) );
		$this->db->execute( 'UPDATE %i SET revoked_at=%s WHERE conversation_id=%d AND revoked_at IS NULL', array( $this->tables->access_tokens(), $this->dates->format( $now ), $conversation_id ) );
	}

	/**
	 * Require one conditional write or roll back the transaction.
	 *
	 * @param int $affected Affected row count.
	 * @throws AdminFinderReportDecisionConflict When the row changed concurrently.
	 */
	private function require_one( int $affected ): void {
		if ( 1 !== $affected ) {
			throw new AdminFinderReportDecisionConflict(); }
	}
}
