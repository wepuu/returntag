<?php
/**
 * Finder email OTP rate-limit port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;

interface FinderEmailRateLimiter {
	/**
	 * Atomically reserve keyed email and peer budgets.
	 *
	 * @param LookupDigest      $email Keyed email lookup.
	 * @param LookupDigest      $peer Keyed peer lookup.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve_request( LookupDigest $email, LookupDigest $peer, DateTimeImmutable $now ): bool;

	/**
	 * Atomically reserve keyed verification-attempt budgets.
	 *
	 * @param LookupDigest      $email Keyed email lookup.
	 * @param LookupDigest      $peer Keyed peer lookup.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve_verification( LookupDigest $email, LookupDigest $peer, DateTimeImmutable $now ): bool;
}
