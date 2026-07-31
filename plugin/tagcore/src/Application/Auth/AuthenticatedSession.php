<?php
/**
 * Authenticated WordPress session boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

/**
 * Exposes only the session operations required by passwordless activation.
 */
interface AuthenticatedSession {
	/**
	 * Return the current authenticated WordPress User ID.
	 */
	public function current_user_id(): ?int;

	/**
	 * Establish a fresh non-persistent authenticated session.
	 *
	 * @param int $user_id Positive WordPress User ID.
	 */
	public function authenticate( int $user_id ): void;
}
