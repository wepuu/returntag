<?php
/**
 * WordPress integration tests for schema version persistence and locking.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use WP_UnitTestCase;

/**
 * Verifies site-scoped option behavior against the test database.
 */
final class WordPressSchemaVersionStoreTest extends WP_UnitTestCase {
	/**
	 * Remove the schema option before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}

	/**
	 * Remove the schema option after each test.
	 */
	protected function tearDown(): void {
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );

		parent::tearDown();
	}

	/**
	 * Missing and malformed values must never imply a completed migration.
	 */
	public function test_missing_and_invalid_values_fail_closed_to_zero(): void {
		$store = new WordPressSchemaVersionStore();

		self::assertSame( 0, $store->current_version() );

		foreach ( array( -1, 'invalid', 1.5, array( 1 ) ) as $invalid_value ) {
			update_option( WordPressSchemaVersionStore::OPTION_NAME, $invalid_value, false );
			self::assertSame( 0, $store->current_version() );
		}
	}

	/**
	 * Applied versions use the site option table without autoloading.
	 */
	public function test_applied_version_is_site_scoped_and_not_autoloaded(): void {
		global $wpdb;

		$store = new WordPressSchemaVersionStore();
		$store->mark_applied( 1 );

		self::assertSame( 1, $store->current_version() );

		$autoload = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
				WordPressSchemaVersionStore::OPTION_NAME
			)
		);

		self::assertContains( $autoload, array( 'no', 'off' ) );
	}

	/**
	 * The database advisory lock is releasable and reusable.
	 */
	public function test_database_advisory_lock_can_be_released_and_reacquired(): void {
		global $wpdb;

		$lock = new WordPressAdvisoryMigrationLock( $wpdb, get_current_blog_id(), 0 );

		self::assertTrue( $lock->acquire() );
		$lock->release();
		self::assertTrue( $lock->acquire() );
		$lock->release();
	}
}
