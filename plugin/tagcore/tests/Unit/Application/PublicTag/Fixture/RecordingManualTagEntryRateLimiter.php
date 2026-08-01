<?php
/**
 * Recording manual-entry limiter fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\PublicTag\Fixture;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\PublicTag\ManualTagEntryRateLimiter;

/**
 * Records one bounded reservation decision.
 */
final class RecordingManualTagEntryRateLimiter implements ManualTagEntryRateLimiter {
	/**
	 * Number of reservations.
	 *
	 * @var int
	 */
	public int $reservations = 0;

	/**
	 * Create the fixture.
	 *
	 * @param bool $allowed Reservation decision.
	 */
	public function __construct( private readonly bool $allowed ) {
	}

	/**
	 * Record and return the configured decision.
	 *
	 * @param LookupDigest      $ip_lookup Unused keyed lookup.
	 * @param DateTimeImmutable $now Unused current time.
	 */
	public function reserve( LookupDigest $ip_lookup, DateTimeImmutable $now ): bool {
		unset( $ip_lookup, $now );
		++$this->reservations;

		return $this->allowed;
	}
}
