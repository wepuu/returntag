<?php
/**
 * Activation OTP verification rate-limiter port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Reserves privacy-safe budgets without allocating attacker-selected identities.
 */
interface ActivationOtpVerificationRateLimiter {
	/**
	 * Reserve one public verification attempt before challenge lookup.
	 *
	 * @param LookupDigest      $ip_lookup Keyed direct-peer IP digest.
	 * @param TagId             $tag_id Public Tag.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve_public(
		LookupDigest $ip_lookup,
		TagId $tag_id,
		DateTimeImmutable $now
	): bool;

	/**
	 * Reserve the email scope only after challenge eligibility is established.
	 *
	 * @param LookupDigest      $email_lookup Keyed email digest.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve_email( LookupDigest $email_lookup, DateTimeImmutable $now ): bool;
}
