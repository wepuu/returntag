<?php
/**
 * Passwordless account Event identity policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use ReturnTag\TagCore\Application\Persistence\EventIdentityPolicy;

/**
 * Allows only the metadata-free passwordless account creation Event.
 */
final class PasswordlessAccountEventIdentityPolicy implements EventIdentityPolicy {
	public const ACCOUNT_CREATED = 'account_passwordless_created';

	/**
	 * Determine whether one Event identity is approved.
	 *
	 * @param string      $event_type Event classification.
	 * @param string      $actor_type Actor classification.
	 * @param int|null    $actor_id Internal actor identifier.
	 * @param string      $target_type Target classification.
	 * @param string      $target_id Target identifier.
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
		return self::ACCOUNT_CREATED === $event_type
			&& 'system' === $actor_type
			&& null === $actor_id
			&& 'user' === $target_type
			&& 1 === preg_match( '/^[1-9][0-9]*$/D', $target_id )
			&& null === $correlation_id;
	}
}
