<?php
/**
 * Owner Tag mutation Event identity policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use ReturnTag\TagCore\Application\Persistence\EventIdentityPolicy;
use ReturnTag\TagCore\Domain\Tag\TagId;

/** Allows only fixed, metadata-free Stage 2 Event identities. */
final class OwnerTagMutationEventIdentityPolicy implements EventIdentityPolicy {
	public const METADATA_UPDATED = 'owner_tag_metadata_updated';

	public const LOST_STATE_UPDATED = 'owner_tag_lost_state_updated';

	public const SMART_SETUP_ACKNOWLEDGED = 'owner_smart_setup_acknowledged';

	/**
	 * Determine whether one Event identity is approved.
	 *
	 * @param string      $event_type Canonical event type.
	 * @param string      $actor_type Canonical actor type.
	 * @param int|null    $actor_id Current Owner identifier.
	 * @param string      $target_type Canonical target type.
	 * @param string      $target_id Canonical Tag identifier.
	 * @param string|null $correlation_id Optional correlation identifier.
	 */
	public function allows(
		string $event_type,
		string $actor_type,
		?int $actor_id,
		string $target_type,
		string $target_id,
		?string $correlation_id
	): bool {
		return in_array(
			$event_type,
			array( self::METADATA_UPDATED, self::LOST_STATE_UPDATED, self::SMART_SETUP_ACKNOWLEDGED ),
			true
		)
			&& 'user' === $actor_type
			&& null !== $actor_id
			&& $actor_id > 0
			&& 'tag' === $target_type
			&& TagId::LENGTH === strlen( $target_id )
			&& TagId::LENGTH === strspn( $target_id, TagId::ALPHABET )
			&& null === $correlation_id;
	}
}
