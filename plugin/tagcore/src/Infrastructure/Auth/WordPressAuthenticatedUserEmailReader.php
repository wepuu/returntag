<?php
/**
 * WordPress authenticated User email adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Auth;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Auth\AuthenticatedUserEmailReader;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use WP_User;

/**
 * Reads one existing WordPress User email without exposing profile data.
 */
final class WordPressAuthenticatedUserEmailReader implements AuthenticatedUserEmailReader {
	/**
	 * Return one canonical existing User email.
	 *
	 * @param int $user_id Positive server-derived User ID.
	 */
	public function find( int $user_id ): ?EmailAddress {
		if ( $user_id < 1 ) {
			return null;
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user instanceof WP_User ) {
			return null;
		}

		try {
			return new EmailAddress( $user->user_email );
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}
}
