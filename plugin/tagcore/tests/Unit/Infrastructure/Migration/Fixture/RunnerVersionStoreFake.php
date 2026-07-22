<?php
/**
 * Schema version store test fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Migration\Fixture;

use ReturnTag\TagCore\Infrastructure\Migration\SchemaVersionStore;

/**
 * In-memory schema version store with observable writes.
 */
final class RunnerVersionStoreFake implements SchemaVersionStore {
	/**
	 * Versions marked as successfully applied.
	 *
	 * @var list<int>
	 */
	public array $marked_versions = array();

	/**
	 * Create a version store fixture.
	 *
	 * @param int $version Initial version.
	 */
	public function __construct( private int $version ) {
	}

	/**
	 * Return the current in-memory version.
	 */
	public function current_version(): int {
		return $this->version;
	}

	/**
	 * Advance and record the in-memory version.
	 *
	 * @param int $version Applied version.
	 */
	public function mark_applied( int $version ): void {
		$this->version           = $version;
		$this->marked_versions[] = $version;
	}
}
