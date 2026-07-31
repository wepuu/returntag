<?php
/**
 * Authenticated WordPress User email boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/**
 * Reads only the canonical email needed for server-side activation limits.
 */
interface AuthenticatedUserEmailReader {
	/**
	 * Return the canonical email for one server-derived User ID.
	 *
	 * @param int $user_id Positive WordPress User ID.
	 */
	public function find( int $user_id ): ?EmailAddress;
}
