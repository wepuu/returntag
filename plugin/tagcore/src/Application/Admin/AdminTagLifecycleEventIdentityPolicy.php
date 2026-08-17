<?php
/**
 * Administrator Tag lifecycle Event identity policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

use ReturnTag\TagCore\Application\Persistence\EventIdentityPolicy;
use ReturnTag\TagCore\Domain\Tag\TagId;

/** Allows only operator-to-Tag identities for RT-327 Events. */
final class AdminTagLifecycleEventIdentityPolicy implements EventIdentityPolicy {
	/**
	 * Determine whether one Event identity is approved.
	 *
	 * @param string      $event_type Event classification.
	 * @param string      $actor_type Actor classification.
	 * @param int|null    $actor_id Internal operator User ID.
	 * @param string      $target_type Target classification.
	 * @param string      $target_id Canonical Tag ID.
	 * @param string|null $correlation_id Optional correlation identifier.
	 */
	public function allows( string $event_type, string $actor_type, ?int $actor_id, string $target_type, string $target_id, ?string $correlation_id ): bool {
		return in_array( $event_type, array( 'tag_suspended', 'tag_retired', 'tag_owner_removed', 'tag_transferred' ), true )
			&& 'user' === $actor_type
			&& null !== $actor_id
			&& $actor_id > 0
			&& 'tag' === $target_type
			&& TagId::LENGTH === strlen( $target_id )
			&& TagId::LENGTH === strspn( $target_id, TagId::ALPHABET )
			&& null === $correlation_id;
	}
}
