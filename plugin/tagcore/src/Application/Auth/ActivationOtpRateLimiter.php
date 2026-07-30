<?php
/**
 * Activation OTP rate-limiter port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Reserves privacy-safe IP and global request budgets atomically.
 */
interface ActivationOtpRateLimiter {
	/**
	 * Atomically reserve IP and global budgets.
	 *
	 * @param LookupDigest      $ip_lookup Keyed IP digest.
	 * @param LookupDigest      $email_lookup Keyed email digest.
	 * @param TagId             $tag_id Public Tag.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve(
		LookupDigest $ip_lookup,
		LookupDigest $email_lookup,
		TagId $tag_id,
		DateTimeImmutable $now
	): bool;
}
