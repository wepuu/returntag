<?php
/**
 * Ordered migration registry.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

/**
 * Validates and exposes the complete numbered migration sequence.
 */
final class MigrationRegistry {
	/**
	 * Registered migrations in ascending version order.
	 *
	 * @var list<Migration>
	 */
	private array $migrations;

	/**
	 * Build a strictly ordered, gap-free registry starting at version one.
	 *
	 * @param array $migrations Migrations in version order.
	 * @phpstan-param list<Migration> $migrations
	 * @throws MigrationRegistryException When numbering or names are invalid.
	 */
	public function __construct( array $migrations ) {
		$expected_version = 1;
		$seen_versions    = array();

		foreach ( $migrations as $migration ) {
			$version = $migration->version();

			if ( isset( $seen_versions[ $version ] ) ) {
				throw new MigrationRegistryException( 'Migration versions must be unique.' );
			}

			if ( $version !== $expected_version ) {
				throw new MigrationRegistryException( 'Migration versions must be ordered and contiguous from one.' );
			}

			if ( '' === trim( $migration->name() ) ) {
				throw new MigrationRegistryException( 'Migration names must not be empty.' );
			}

			$seen_versions[ $version ] = true;
			++$expected_version;
		}

		$this->migrations = $migrations;
	}

	/**
	 * Return every registered migration in ascending version order.
	 *
	 * @return list<Migration>
	 */
	public function all(): array {
		return $this->migrations;
	}

	/**
	 * Return the schema version represented by the registry.
	 */
	public function target_version(): int {
		if ( array() === $this->migrations ) {
			return 0;
		}

		return $this->migrations[ array_key_last( $this->migrations ) ]->version();
	}
}
