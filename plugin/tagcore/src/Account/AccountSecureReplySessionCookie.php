<?php
/**
 * Account-issued Secure Reply session cookie.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

/** Applies the existing role-bound Secure Reply cookie contract. */
final class AccountSecureReplySessionCookie {
	public const NAME = 'returntag_reply_session';

	/**
	 * Set one HttpOnly, Strict, route-scoped 30-minute session.
	 *
	 * @param string $session Raw role-bound Conversation session.
	 */
	public function set( string $session ): bool {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{43}$/D', $session ) ) {
			return false;
		}

		return setcookie(
			self::NAME,
			$session,
			array(
				'expires'  => time() + 1800,
				'path'     => '/secure-reply/',
				'secure'   => true,
				'httponly' => true,
				'samesite' => 'Strict',
			)
		);
	}
}
