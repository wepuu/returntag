<?php
/**
 * Manual Tag entry rate-limit contract.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\PublicTag;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;

/**
 * Reserves privacy-safe public entry budgets.
 */
interface ManualTagEntryRateLimiter {
	/**
	 * Reserve the direct-peer and global budgets atomically.
	 *
	 * @param LookupDigest      $ip_lookup Keyed direct-peer IP digest.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve( LookupDigest $ip_lookup, DateTimeImmutable $now ): bool;
}
