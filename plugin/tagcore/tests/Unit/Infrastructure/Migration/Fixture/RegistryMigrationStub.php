<?php
/**
 * Registry migration test fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Migration\Fixture;

use ReturnTag\TagCore\Infrastructure\Migration\Migration;

/**
 * Minimal immutable migration used to validate registry construction.
 */
final class RegistryMigrationStub implements Migration {
	/**
	 * Create a migration fixture.
	 *
	 * @param int    $migration_version Fixture version.
	 * @param string $migration_name    Fixture name.
	 */
	public function __construct(
		private readonly int $migration_version,
		private readonly string $migration_name
	) {
	}

	/**
	 * Return the fixture version.
	 */
	public function version(): int {
		return $this->migration_version;
	}

	/**
	 * Return the fixture name.
	 */
	public function name(): string {
		return $this->migration_name;
	}

	/**
	 * Apply no schema change in this registry-only fixture.
	 */
	public function up(): void {
	}

	/**
	 * Report a valid fixture postcondition.
	 */
	public function verify(): bool {
		return true;
	}
}
