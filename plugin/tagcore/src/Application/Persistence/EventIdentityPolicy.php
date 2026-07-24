<?php
/**
 * Event identity policy contract.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence;

/**
 * Approves exact Event actor, target, and correlation identity combinations.
 */
interface EventIdentityPolicy {
	/**
	 * Determine whether one Event identity is approved for persistence.
	 *
	 * @param string      $event_type Event classification.
	 * @param string      $actor_type Actor classification.
	 * @param int|null    $actor_id Internal actor identifier.
	 * @param string      $target_type Target classification.
	 * @param string      $target_id Opaque target identifier.
	 * @param string|null $correlation_id Operation correlation identifier.
	 */
	public function allows(
		string $event_type,
		string $actor_type,
		?int $actor_id,
		string $target_type,
		string $target_id,
		?string $correlation_id
	): bool;
}
