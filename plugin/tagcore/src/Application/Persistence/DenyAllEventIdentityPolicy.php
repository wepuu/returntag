<?php
/**
 * Default Event identity policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence;

/**
 * Fails closed until a product ticket approves exact Event identities.
 */
final class DenyAllEventIdentityPolicy implements EventIdentityPolicy {
	/**
	 * Reject every Event identity.
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
	): bool {
		unset( $event_type, $actor_type, $actor_id, $target_type, $target_id, $correlation_id );

		return false;
	}
}
