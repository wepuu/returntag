<?php
/**
 * Owner Test Email Event identity policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use ReturnTag\TagCore\Application\Persistence\EventIdentityPolicy;

/** Allows only fixed Test Email Event identities. */
final class OwnerTestEmailEventIdentityPolicy implements EventIdentityPolicy {
	/**
	 * Determine whether one Test Email Event identity is approved.
	 *
	 * @param string      $event_type Event classification.
	 * @param string      $actor_type Actor classification.
	 * @param int|null    $actor_id Internal actor identifier.
	 * @param string      $target_type Target classification.
	 * @param string      $target_id Owner identifier.
	 * @param string|null $correlation_id Optional request Event identifier.
	 */
	public function allows( string $event_type, string $actor_type, ?int $actor_id, string $target_type, string $target_id, ?string $correlation_id ): bool {
		return in_array( $event_type, array( 'owner_test_email_requested', 'owner_test_email_accepted', 'owner_test_email_failed' ), true )
			&& 'user' === $actor_type && null !== $actor_id && $actor_id > 0
			&& 'user' === $target_type && (string) $actor_id === $target_id
			&& ( 'owner_test_email_requested' === $event_type ? null === $correlation_id : null !== $correlation_id && ctype_digit( $correlation_id ) );
	}
}
