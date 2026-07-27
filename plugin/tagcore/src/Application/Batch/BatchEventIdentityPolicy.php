<?php
/**
 * Batch Event identity policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Application\Persistence\EventIdentityPolicy;

/**
 * Allows only the privacy-safe RT-201 Batch creation Event identity.
 */
final class BatchEventIdentityPolicy implements EventIdentityPolicy {
	/**
	 * Determine whether one Event identity is approved.
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
		return 'batch.created' === $event_type
			&& 'user' === $actor_type
			&& null !== $actor_id
			&& $actor_id > 0
			&& 'batch' === $target_type
			&& 1 === preg_match( '/^[1-9][0-9]*$/D', $target_id )
			&& null === $correlation_id;
	}
}
