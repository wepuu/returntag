<?php
/**
 * Owner Test Email rate-limit port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use DateTimeImmutable;

interface OwnerTestEmailRateLimiter {
	/**
	 * Reserve one current-Owner and direct-peer request budget.
	 *
	 * @param int               $owner_id Server-derived Owner identifier.
	 * @param string            $ip_address Direct-peer IP address.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve( int $owner_id, string $ip_address, DateTimeImmutable $now ): bool;
}
