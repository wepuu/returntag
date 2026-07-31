<?php
/**
 * Tag activation Event identity policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use ReturnTag\TagCore\Application\Persistence\EventIdentityPolicy;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Allows only the metadata-free first-activation Event.
 */
final class TagActivationEventIdentityPolicy implements EventIdentityPolicy {
	public const TAG_ACTIVATED = 'tag_activated';

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
		return self::TAG_ACTIVATED === $event_type
			&& 'user' === $actor_type
			&& null !== $actor_id
			&& $actor_id > 0
			&& 'tag' === $target_type
			&& TagId::LENGTH === strlen( $target_id )
			&& TagId::LENGTH === strspn( $target_id, TagId::ALPHABET )
			&& null === $correlation_id;
	}
}
