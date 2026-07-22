<?php
/**
 * Migration lock test fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Migration\Fixture;

use ReturnTag\TagCore\Infrastructure\Migration\MigrationLock;

/**
 * In-memory lock with observable acquisition and release counts.
 */
final class RunnerLockFake implements MigrationLock {
	/**
	 * Whether acquisition succeeds.
	 *
	 * @var bool
	 */
	public bool $available = true;

	/**
	 * Number of acquisition attempts.
	 *
	 * @var int
	 */
	public int $acquire_count = 0;

	/**
	 * Number of releases.
	 *
	 * @var int
	 */
	public int $release_count = 0;

	/**
	 * Attempt to obtain the fixture lock.
	 */
	public function acquire(): bool {
		++$this->acquire_count;

		return $this->available;
	}

	/**
	 * Record one lock release.
	 */
	public function release(): void {
		++$this->release_count;
	}
}
