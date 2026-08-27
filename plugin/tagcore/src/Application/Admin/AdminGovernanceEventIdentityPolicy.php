<?php
/**
 * RT-329 metadata-free retention Event identities.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

use ReturnTag\TagCore\Application\Persistence\EventIdentityPolicy;

/** Restricts retention Events to fixed task identifiers and safe actors. */
final class AdminGovernanceEventIdentityPolicy implements EventIdentityPolicy {
	private const EVENTS = array( 'retention_task_run_requested', 'retention_task_run_completed', 'retention_task_run_failed' );
	private const TASKS  = array( 'auth_challenge_cleanup', 'activation_cleanup', 'account_cleanup', 'finder_rate_cleanup', 'finder_evidence_cleanup' );

	/**
	 * Determine whether the Event identity is permitted.
	 *
	 * @param string      $event_type Event classification.
	 * @param string      $actor_type Actor classification.
	 * @param int|null    $actor_id Nullable actor identity.
	 * @param string      $target_type Target classification.
	 * @param string      $target_id Target identity.
	 * @param string|null $correlation_id Nullable correlation identity.
	 */
	public function allows( string $event_type, string $actor_type, ?int $actor_id, string $target_type, string $target_id, ?string $correlation_id ): bool {
		$actor_ok = 'retention_task_run_requested' === $event_type
			? 'user' === $actor_type && null !== $actor_id && $actor_id > 0
			: 'system' === $actor_type && null === $actor_id;
		return in_array( $event_type, self::EVENTS, true ) && $actor_ok && 'retention_task' === $target_type && in_array( $target_id, self::TASKS, true ) && null === $correlation_id;
	}
}
