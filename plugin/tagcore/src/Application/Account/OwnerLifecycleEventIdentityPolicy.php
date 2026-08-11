<?php
/**
 * Owner lifecycle Event identity policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use ReturnTag\TagCore\Application\Persistence\EventIdentityPolicy;
use ReturnTag\TagCore\Domain\Tag\TagId;


/** Allows only metadata-free Transfer and Retire Event identities. */
final class OwnerLifecycleEventIdentityPolicy implements EventIdentityPolicy {
	/**
	 * Determine whether one lifecycle Event identity is approved.
	 *
	 * @param string      $event_type Event classification.
	 * @param string      $actor_type Actor classification.
	 * @param int|null    $actor_id Internal actor identifier.
	 * @param string      $target_type Target classification.
	 * @param string      $target_id Canonical Tag ID.
	 * @param string|null $correlation_id Optional Transfer identifier.
	 */
	public function allows( string $event_type, string $actor_type, ?int $actor_id, string $target_type, string $target_id, ?string $correlation_id ): bool {
		if ( ! in_array( $event_type, array( 'tag_transferred', 'tag_retired' ), true ) || 'user' !== $actor_type || null === $actor_id || $actor_id < 1 || 'tag' !== $target_type || TagId::LENGTH !== strlen( $target_id ) || TagId::LENGTH !== strspn( $target_id, TagId::ALPHABET ) ) {
			return false; }
		return 'tag_transferred' === $event_type ? null !== $correlation_id && ctype_digit( $correlation_id ) : null === $correlation_id;
	}
}
