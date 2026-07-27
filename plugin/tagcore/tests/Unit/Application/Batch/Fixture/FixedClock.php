<?php
/**
 * Fixed Clock test fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Clock;

/**
 * Returns one deterministic timestamp.
 */
final readonly class FixedClock implements Clock {
	/**
	 * Create the clock.
	 *
	 * @param DateTimeImmutable $time Fixed UTC time.
	 */
	public function __construct( private DateTimeImmutable $time ) {
	}

	/**
	 * Return the fixed time.
	 */
	public function now(): DateTimeImmutable {
		return $this->time;
	}
}
