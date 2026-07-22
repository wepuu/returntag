<?php
/**
 * Numbered migration runner.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use Throwable;

/**
 * Applies and verifies pending migrations while holding the site lock.
 */
final class MigrationRunner {
	/**
	 * Create a migration runner.
	 *
	 * @param MigrationRegistry  $registry      Validated migration registry.
	 * @param SchemaVersionStore $version_store Site-scoped version store.
	 * @param MigrationLock      $lock          Site-scoped advisory lock.
	 */
	public function __construct(
		private readonly MigrationRegistry $registry,
		private readonly SchemaVersionStore $version_store,
		private readonly MigrationLock $lock
	) {
	}

	/**
	 * Apply every migration after the last verified schema version.
	 *
	 * @throws MigrationException When locking, execution, or verification fails.
	 */
	public function migrate(): MigrationReport {
		$starting_version = $this->version_store->current_version();
		$target_version   = $this->registry->target_version();

		$this->assert_supported_version( $starting_version, $target_version );

		if ( $starting_version === $target_version ) {
			return new MigrationReport( $starting_version, $starting_version, array() );
		}

		if ( ! $this->lock->acquire() ) {
			throw new MigrationException( 'The migration lock is currently unavailable.' );
		}

		$applied_versions = array();

		try {
			$current_version = $this->version_store->current_version();
			$this->assert_supported_version( $current_version, $target_version );

			foreach ( $this->registry->all() as $migration ) {
				if ( $migration->version() <= $current_version ) {
					continue;
				}

				$migration->up();

				if ( ! $migration->verify() ) {
					throw new MigrationException( 'A migration failed postcondition verification.' );
				}

				$this->version_store->mark_applied( $migration->version() );
				$current_version    = $migration->version();
				$applied_versions[] = $current_version;
			}

			return new MigrationReport( $starting_version, $current_version, $applied_versions );
		} catch ( Throwable ) {
			throw new MigrationException( 'A migration could not be completed.' );
		} finally {
			$this->lock->release();
		}
	}

	/**
	 * Refuse to run code that does not understand the stored schema version.
	 *
	 * @param int $current_version Stored site schema version.
	 * @param int $target_version  Highest migration understood by this code.
	 * @throws MigrationException When stored schema is newer than supported.
	 */
	private function assert_supported_version( int $current_version, int $target_version ): void {
		if ( $current_version > $target_version ) {
			throw new MigrationException( 'The stored schema version is newer than this plugin supports.' );
		}
	}
}
