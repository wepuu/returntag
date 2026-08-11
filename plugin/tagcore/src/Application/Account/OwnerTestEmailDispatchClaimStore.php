<?php
/**
 * Owner Test Email dispatch claim port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use DateTimeImmutable;

interface OwnerTestEmailDispatchClaimStore {
	/**
	 * Atomically claim one request Event for at-most-once dispatch.
	 *
	 * @param int               $event_id Request Event identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function claim( int $event_id, DateTimeImmutable $now ): bool;

	/**
	 * Delete a bounded number of expired opaque claims.
	 *
	 * @param int $limit Maximum candidate Options to inspect.
	 */
	public function cleanup_expired( int $limit = 500 ): int;
}
