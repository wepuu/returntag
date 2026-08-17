<?php
/**
 * WordPress database administrator Tag lifecycle adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Admin\AdminTagLifecycleAction;
use ReturnTag\TagCore\Application\Admin\AdminTagLifecyclePolicy;
use ReturnTag\TagCore\Application\Admin\AdminTagLifecycleResult;
use ReturnTag\TagCore\Application\Admin\AdminTagLifecycleState;
use ReturnTag\TagCore\Application\Admin\AdminTagLifecycleStore;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\EventMetadataPolicy;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ValueError;

/** Applies state, revocation, Transfer cancellation, and audit in one transaction. */
final readonly class WpdbAdminTagLifecycleStore implements AdminTagLifecycleStore {
	/**
	 * Create the persistence adapter.
	 *
	 * @param WpdbGateway             $db Prepared database gateway.
	 * @param TableNames              $tables Trusted table names.
	 * @param string                  $users_table Trusted WordPress users table.
	 * @param DatabaseDateTimeCodec   $dates UTC database date codec.
	 * @param TransactionManager      $transactions Transaction boundary.
	 * @param EventRepository         $events Audit Event store.
	 * @param EventMetadataPolicy     $metadata_policy Lifecycle metadata allowlist.
	 * @param AdminTagLifecyclePolicy $policy Pure transition policy.
	 */
	public function __construct(
		private WpdbGateway $db,
		private TableNames $tables,
		private string $users_table,
		private DatabaseDateTimeCodec $dates,
		private TransactionManager $transactions,
		private EventRepository $events,
		private EventMetadataPolicy $metadata_policy,
		private AdminTagLifecyclePolicy $policy
	) {
	}

	/**
	 * Execute one conditional, fully audited lifecycle transaction.
	 *
	 * @param TagId                   $tag_id Canonical Tag ID.
	 * @param AdminTagLifecycleAction $action Administrator action.
	 * @param AdminTagLifecycleState  $expected Submitted current-state snapshot.
	 * @param int|null                $target_user_id Optional target User ID.
	 * @param int                     $operator_id Operator WordPress User ID.
	 * @param DateTimeImmutable       $now Current UTC time.
	 */
	public function change(
		TagId $tag_id,
		AdminTagLifecycleAction $action,
		AdminTagLifecycleState $expected,
		?int $target_user_id,
		int $operator_id,
		DateTimeImmutable $now
	): AdminTagLifecycleResult {
		$event_type = match ( $action ) {
			AdminTagLifecycleAction::SUSPEND => 'tag_suspended',
			AdminTagLifecycleAction::RETIRE => 'tag_retired',
			AdminTagLifecycleAction::REMOVE_OWNER => 'tag_owner_removed',
			AdminTagLifecycleAction::TRANSFER_OWNER => 'tag_transferred',
		};

		return $this->transactions->transactional(
			function () use ( $tag_id, $action, $expected, $target_user_id, $operator_id, $now, $event_type ): AdminTagLifecycleResult {
				$row = $this->db->row(
					'SELECT tag_status, owner_id FROM %i WHERE tag_id=%s FOR UPDATE',
					array( $this->tables->tags(), $tag_id->value )
				);
				if ( null === $row ) {
					return AdminTagLifecycleResult::unavailable();
				}

				try {
					$before = new AdminTagLifecycleState(
						TagStatus::from( (string) $row['tag_status'] ),
						null === $row['owner_id'] ? null : (int) $row['owner_id']
					);
				} catch ( ValueError ) {
					return AdminTagLifecycleResult::unavailable();
				}

				if ( $before->status !== $expected->status || $before->owner_id !== $expected->owner_id ) {
					return AdminTagLifecycleResult::unavailable();
				}

				if ( AdminTagLifecycleAction::TRANSFER_OWNER === $action && ! $this->target_is_unique( $target_user_id ) ) {
					return AdminTagLifecycleResult::unavailable();
				}

				$after = $this->policy->decide( $action, $before, $target_user_id );
				if ( null === $after || 1 !== $this->update_tag( $tag_id, $before, $after, $now ) ) {
					return AdminTagLifecycleResult::unavailable();
				}

				$this->revoke_tag_access( $tag_id->value, $now );
				$this->cancel_pending_transfers( $tag_id->value, $now );
				$this->fail_pending_owner_notifications( $tag_id->value, $now );
				$this->events->append(
					new NewEventRecord(
						$event_type,
						'user',
						$operator_id,
						'tag',
						$tag_id->value,
						'success',
						null,
						EventMetadata::from_values(
							$event_type,
							array(
								'before_status'   => $before->status->value,
								'after_status'    => $after->status->value,
								'before_owner_id' => $before->owner_id,
								'after_owner_id'  => $after->owner_id,
							),
							$this->metadata_policy
						),
						$now
					)
				);

				return AdminTagLifecycleResult::changed( $after );
			}
		);
	}

	/**
	 * Require an existing target whose valid email maps to exactly that User ID.
	 *
	 * @param int|null $target_user_id Candidate WordPress User ID.
	 */
	private function target_is_unique( ?int $target_user_id ): bool {
		if ( null === $target_user_id || $target_user_id < 1 ) {
			return false;
		}

		$user = $this->db->row( 'SELECT user_email FROM %i WHERE ID=%d LIMIT 1', array( $this->users_table, $target_user_id ) );
		if ( null === $user || ! is_string( $user['user_email'] ) || false === is_email( $user['user_email'] ) ) {
			return false;
		}

		$matches = $this->db->rows( 'SELECT ID FROM %i WHERE user_email=%s ORDER BY ID ASC LIMIT 2', array( $this->users_table, $user['user_email'] ) );
		return 1 === count( $matches ) && $target_user_id === (int) $matches[0]['ID'];
	}

	/**
	 * Apply the conditional Tag update without interpolating a nullable Owner.
	 *
	 * @param TagId                  $tag_id Canonical Tag ID.
	 * @param AdminTagLifecycleState $before Locked current state.
	 * @param AdminTagLifecycleState $after Approved committed state.
	 * @param DateTimeImmutable      $now Current UTC time.
	 */
	private function update_tag( TagId $tag_id, AdminTagLifecycleState $before, AdminTagLifecycleState $after, DateTimeImmutable $now ): int {
		$set_owner = $before->owner_id !== $after->owner_id;
		$set       = 'tag_status=%s, updated_at=%s';
		$args      = array( $this->tables->tags(), $after->status->value, $this->dates->format( $now ) );

		if ( $set_owner ) {
			$set .= null === $after->owner_id ? ', owner_id=NULL, owner_changed_at=%s' : ', owner_id=%d, owner_changed_at=%s';
			if ( null !== $after->owner_id ) {
				$args[] = $after->owner_id;
			}
			$args[] = $this->dates->format( $now );
		}

		$where  = 'tag_id=%s AND tag_status=%s';
		$args[] = $tag_id->value;
		$args[] = $before->status->value;
		if ( null === $before->owner_id ) {
			$where .= ' AND owner_id IS NULL';
		} else {
			$where .= ' AND owner_id=%d';
			$args[] = $before->owner_id;
		}

		return $this->db->execute( 'UPDATE %i SET ' . $set . ' WHERE ' . $where, $args );
	}

	/**
	 * Revoke every current Conversation and delivery access path for the Tag.
	 *
	 * @param string            $tag_id Canonical Tag ID.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	private function revoke_tag_access( string $tag_id, DateTimeImmutable $now ): void {
		$this->db->execute( 'UPDATE %i m JOIN %i c ON c.conversation_id=m.conversation_id SET m.delivery_status=%s WHERE c.tag_id=%s AND m.delivery_status IN (%s,%s)', array( $this->tables->messages(), $this->tables->conversations(), 'failed', $tag_id, 'queued', 'in_flight' ) );
		$this->db->execute( 'UPDATE %i SET conversation_status=%s,last_activity_at=%s WHERE tag_id=%s AND conversation_status IN (%s,%s)', array( $this->tables->conversations(), 'closed', $this->dates->format( $now ), $tag_id, 'pending_verification', 'open' ) );
		$this->db->execute( 'UPDATE %i a JOIN %i c ON c.conversation_id=a.conversation_id SET a.revoked_at=%s WHERE c.tag_id=%s AND a.revoked_at IS NULL', array( $this->tables->access_tokens(), $this->tables->conversations(), $this->dates->format( $now ), $tag_id ) );
	}

	/**
	 * Cancel every still-pending ownership Transfer for the Tag.
	 *
	 * @param string            $tag_id Canonical Tag ID.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	private function cancel_pending_transfers( string $tag_id, DateTimeImmutable $now ): void {
		$this->db->execute( 'UPDATE %i SET transfer_status=%s,cancelled_at=%s,updated_at=%s WHERE tag_id=%s AND transfer_status=%s', array( $this->tables->tag_transfers(), 'cancelled', $this->dates->format( $now ), $this->dates->format( $now ), $tag_id, 'pending' ) );
	}

	/**
	 * Make queued or deferred notifications to a prior Owner ineligible.
	 *
	 * @param string            $tag_id Canonical Tag ID.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	private function fail_pending_owner_notifications( string $tag_id, DateTimeImmutable $now ): void {
		$this->db->execute( 'UPDATE %i SET owner_notification_status=%s,updated_at=%s WHERE tag_id=%s AND owner_notification_status IN (%s,%s)', array( $this->tables->finder_reports(), 'failed', $this->dates->format( $now ), $tag_id, 'queued', 'deferred' ) );
	}
}
