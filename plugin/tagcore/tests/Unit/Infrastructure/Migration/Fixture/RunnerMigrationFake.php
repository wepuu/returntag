<?php
/**
 * Executable migration test fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Migration\Fixture;

use ReturnTag\TagCore\Infrastructure\Migration\Migration;
use RuntimeException;

/**
 * Configurable in-memory migration used by runner tests.
 */
final class RunnerMigrationFake implements Migration {
	/**
	 * Number of executions.
	 *
	 * @var int
	 */
	public int $up_count = 0;

	/**
	 * Verification result.
	 *
	 * @var bool
	 */
	public bool $verified = true;

	/**
	 * Whether execution should fail.
	 *
	 * @var bool
	 */
	public bool $up_error = false;

	/**
	 * Create a migration fixture.
	 *
	 * @param int $migration_version Fixture version.
	 */
	public function __construct( private readonly int $migration_version ) {
	}

	/**
	 * Return the fixture version.
	 */
	public function version(): int {
		return $this->migration_version;
	}

	/**
	 * Return a stable fixture name.
	 */
	public function name(): string {
		return 'migration ' . $this->migration_version;
	}

	/**
	 * Record execution and optionally raise a fixture error.
	 *
	 * @throws RuntimeException When configured to simulate failure.
	 */
	public function up(): void {
		++$this->up_count;

		if ( $this->up_error ) {
			throw new RuntimeException( 'Fixture failure detail.' );
		}
	}

	/**
	 * Return the configured verification result.
	 */
	public function verify(): bool {
		return $this->verified;
	}
}
