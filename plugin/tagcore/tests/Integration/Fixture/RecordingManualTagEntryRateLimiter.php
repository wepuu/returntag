<?php
/**
 * Recording manual-entry integration fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration\Fixture;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\PublicTag\ManualTagEntryRateLimiter;

/**
 * Allows and records manual-entry reservations without database queries.
 */
final class RecordingManualTagEntryRateLimiter implements ManualTagEntryRateLimiter {
	/**
	 * Number of reservations.
	 *
	 * @var int
	 */
	public int $reservations = 0;

	/**
	 * Record and allow one reservation.
	 *
	 * @param LookupDigest      $ip_lookup Unused keyed lookup.
	 * @param DateTimeImmutable $now Unused current time.
	 */
	public function reserve( LookupDigest $ip_lookup, DateTimeImmutable $now ): bool {
		unset( $ip_lookup, $now );
		++$this->reservations;

		return true;
	}
}
