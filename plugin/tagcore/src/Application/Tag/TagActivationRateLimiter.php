<?php
/**
 * Authenticated Tag activation rate-limit port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Atomically reserves all server-derived activation-attempt budgets.
 */
interface TagActivationRateLimiter {
	/**
	 * Reserve one attempt across User, email, IP, Tag, and global scopes.
	 *
	 * @param int               $owner_id Server-derived WordPress User ID.
	 * @param LookupDigest      $email_lookup Keyed email digest.
	 * @param LookupDigest      $ip_lookup Keyed direct-peer IP digest.
	 * @param TagId             $tag_id Canonical public Tag ID.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve(
		int $owner_id,
		LookupDigest $email_lookup,
		LookupDigest $ip_lookup,
		TagId $tag_id,
		DateTimeImmutable $now
	): bool;
}
