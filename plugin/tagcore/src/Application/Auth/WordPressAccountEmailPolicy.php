<?php
/**
 * WordPress account email compatibility policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/**
 * Keeps activation identity input within the WordPress User storage contract.
 */
final class WordPressAccountEmailPolicy {
	public const MAXIMUM_BYTES = 100;

	/**
	 * Determine whether the canonical ASCII email fits WordPress storage.
	 *
	 * @param EmailAddress $email Canonical email identity.
	 */
	public function allows( EmailAddress $email ): bool {
		return strlen( $email->value ) <= self::MAXIMUM_BYTES;
	}
}
