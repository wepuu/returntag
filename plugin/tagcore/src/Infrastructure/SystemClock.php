<?php
/**
 * System UTC clock.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use ReturnTag\TagCore\Application\Clock;

/**
 * Reads the current time from the PHP runtime in UTC.
 */
final class SystemClock implements Clock {
	/**
	 * Return the current time in UTC.
	 */
	public function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}
}
