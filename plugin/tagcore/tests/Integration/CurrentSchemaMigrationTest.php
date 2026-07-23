<?php
/**
 * Integration tests for the current production Migration composition.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Infrastructure\Migration\CreateAuthChallengesTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\CreateBatchExportsTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\CreateBatchesTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\CreateTagsTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use WP_UnitTestCase;
use wpdb;

/**
 * Keeps current production wiring assertions independent from ticket fixtures.
 */
final class CurrentSchemaMigrationTest extends WP_UnitTestCase {
	/**
	 * Remove current-schema fixtures before every test.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();

		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->clear_schema( $wpdb );
	}

	/**
	 * Remove current-schema fixtures after every test.
	 */
	protected function tearDown(): void {
		global $wpdb;

		$this->clear_schema( $wpdb );

		parent::tearDown();
	}

	/**
	 * Production composition must register contiguous versions one through four.
	 */
	public function test_production_registry_registers_versions_one_through_four(): void {
		global $wpdb;

		$registry   = ( new MigrationRegistryFactory( $wpdb ) )->create();
		$migrations = $registry->all();

		self::assertSame( 4, $registry->target_version() );
		self::assertCount( 4, $migrations );
		self::assertInstanceOf( CreateBatchesTableMigration::class, $migrations[0] );
		self::assertInstanceOf( CreateTagsTableMigration::class, $migrations[1] );
		self::assertInstanceOf( CreateBatchExportsTableMigration::class, $migrations[2] );
		self::assertInstanceOf( CreateAuthChallengesTableMigration::class, $migrations[3] );
		self::assertSame( array( 1, 2, 3, 4 ), array_map( static fn( $migration ): int => $migration->version(), $migrations ) );
	}

	/**
	 * The registered activation hook must execute the current production chain.
	 */
	public function test_plugin_activation_executes_production_chain_to_four(): void {
		global $wpdb;

		do_action( 'activate_' . plugin_basename( RETURNTAG_TAGCORE_FILE ), false );

		$registry = ( new MigrationRegistryFactory( $wpdb ) )->create();
		self::assertSame( 4, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
		self::assertTrue( $registry->all()[3]->verify() );
	}

	/**
	 * Remove only trusted ReturnTag tables from the isolated test database.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function clear_schema( wpdb $database ): void {
		$names = new TableNames( $database->prefix );

		foreach ( array( $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated test cleanup with trusted identifiers.
			$database->query( "DROP TABLE IF EXISTS {$table_name}" );
		}

		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}
}
