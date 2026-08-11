<?php
/**
 * Owner Account OTP rate-limit port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;

interface AccountOtpRateLimiter {
	/**
	 * Reserve one Account OTP request across IP, email, and global scopes.
	 *
	 * @param LookupDigest      $ip_lookup Keyed direct-peer IP digest.
	 * @param LookupDigest      $email_lookup Keyed email digest.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve_request(
		LookupDigest $ip_lookup,
		LookupDigest $email_lookup,
		DateTimeImmutable $now
	): bool;

	/**
	 * Reserve one public verification attempt before challenge lookup.
	 *
	 * @param LookupDigest      $ip_lookup Keyed direct-peer IP digest.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve_verification_ip( LookupDigest $ip_lookup, DateTimeImmutable $now ): bool;

	/**
	 * Reserve the email verification scope only after challenge eligibility.
	 *
	 * @param LookupDigest      $email_lookup Keyed email digest.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve_verification_email( LookupDigest $email_lookup, DateTimeImmutable $now ): bool;
}
