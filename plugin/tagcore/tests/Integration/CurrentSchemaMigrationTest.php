<?php
/**
 * Integration tests for the current production Migration composition.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Infrastructure\Migration\CreateAccessTokensTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\CreateAuthChallengesTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\CreateBatchExportsTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\CreateBatchesTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\CreateConversationsTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\CreateEventsTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\CreateMessagesTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\CreateTagsTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationLifecycle;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistry;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\SchemaState;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
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
	 * Production composition must register contiguous versions one through eight.
	 */
	public function test_production_registry_registers_versions_one_through_eight(): void {
		global $wpdb;

		$registry   = ( new MigrationRegistryFactory( $wpdb ) )->create();
		$migrations = $registry->all();

		self::assertSame( 8, $registry->target_version() );
		self::assertCount( 8, $migrations );
		self::assertInstanceOf( CreateBatchesTableMigration::class, $migrations[0] );
		self::assertInstanceOf( CreateTagsTableMigration::class, $migrations[1] );
		self::assertInstanceOf( CreateBatchExportsTableMigration::class, $migrations[2] );
		self::assertInstanceOf( CreateAuthChallengesTableMigration::class, $migrations[3] );
		self::assertInstanceOf( CreateConversationsTableMigration::class, $migrations[4] );
		self::assertInstanceOf( CreateMessagesTableMigration::class, $migrations[5] );
		self::assertInstanceOf( CreateAccessTokensTableMigration::class, $migrations[6] );
		self::assertInstanceOf( CreateEventsTableMigration::class, $migrations[7] );
		self::assertSame( array( 1, 2, 3, 4, 5, 6, 7, 8 ), array_map( static fn( $migration ): int => $migration->version(), $migrations ) );
	}

	/**
	 * The registered activation hook must execute the current production chain.
	 */
	public function test_plugin_activation_executes_production_chain_to_eight(): void {
		global $wpdb;

		do_action( 'activate_' . plugin_basename( RETURNTAG_TAGCORE_FILE ), false );

		$registry = ( new MigrationRegistryFactory( $wpdb ) )->create();
		self::assertSame( 8, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
		self::assertTrue( $registry->all()[7]->verify() );
		self::assertSame( $this->table_names( $wpdb ), $this->existing_returntag_tables( $wpdb ) );
	}

	/**
	 * A real TagCore upgrade hook must preserve predecessor data while reaching eight.
	 */
	public function test_plugin_upgrade_advances_four_to_eight_and_preserves_data(): void {
		global $wpdb;

		$this->migrate_to( $wpdb, 4 );
		$this->insert_batch_fixture( $wpdb, 'RT110-UPGRADE' );

		$this->lifecycle( $wpdb )->after_plugin_upgrade(
			null,
			array(
				'action' => 'update',
				'type'   => 'plugin',
				'plugin' => plugin_basename( RETURNTAG_TAGCORE_FILE ),
			)
		);

		$batch_table = ( new TableNames( $wpdb->prefix ) )->batches();
		$batch_code  = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT batch_code FROM %i WHERE batch_code = %s',
				$batch_table,
				'RT110-UPGRADE'
			)
		);

		self::assertSame( 8, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
		self::assertSame( 'RT110-UPGRADE', $batch_code );
		self::assertSame( $this->table_names( $wpdb ), $this->existing_returntag_tables( $wpdb ) );
	}

	/**
	 * A complete schema can restore a missing version Option without running DDL.
	 */
	public function test_complete_schema_reconciles_missing_option_without_ddl(): void {
		global $wpdb;

		$this->migrate_to( $wpdb, 8 );
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );

		$ddl_queries = array();
		$observer    = static function ( string $query ) use ( &$ddl_queries ): string {
			if ( 1 === preg_match( '/^\s*(?:ALTER|CREATE|DROP|RENAME|TRUNCATE)\b/i', $query ) ) {
				$ddl_queries[] = $query;
			}

			return $query;
		};

		add_filter( 'query', $observer );

		try {
			$report = $this->runner( $wpdb, 8 )->migrate();
		} finally {
			remove_filter( 'query', $observer );
		}

		self::assertSame( 0, $report->starting_version );
		self::assertSame( 8, $report->ending_version );
		self::assertSame( array( 1, 2, 3, 4, 5, 6, 7, 8 ), $report->applied_versions );
		self::assertSame( 8, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
		self::assertSame( array(), $ddl_queries );
	}

	/**
	 * The guarded uninstall entry point must preserve schema state and records.
	 */
	public function test_uninstall_preserves_schema_option_tables_and_data(): void {
		global $wpdb;

		$this->migrate_to( $wpdb, 8 );
		$this->insert_batch_fixture( $wpdb, 'RT110-UNINSTALL' );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		require dirname( __DIR__, 2 ) . '/uninstall.php';

		$batch_table = ( new TableNames( $wpdb->prefix ) )->batches();
		$batch_count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE batch_code = %s',
				$batch_table,
				'RT110-UNINSTALL'
			)
		);

		self::assertSame( 8, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
		self::assertSame( '1', (string) $batch_count );
		self::assertSame( $this->table_names( $wpdb ), $this->existing_returntag_tables( $wpdb ) );
	}

	/**
	 * Apply the production chain through a requested target version.
	 *
	 * @param wpdb $database WordPress database adapter.
	 * @param int  $target_version Requested target version.
	 */
	private function migrate_to( wpdb $database, int $target_version ): void {
		$report = $this->runner( $database, $target_version )->migrate();

		self::assertSame( $target_version, $report->ending_version );
	}

	/**
	 * Build a real runner over the production Migration prefix.
	 *
	 * @param wpdb $database WordPress database adapter.
	 * @param int  $target_version Requested target version.
	 */
	private function runner( wpdb $database, int $target_version ): MigrationRunner {
		$production = ( new MigrationRegistryFactory( $database ) )->create()->all();
		$registry   = new MigrationRegistry( array_slice( $production, 0, $target_version ) );

		return new MigrationRunner(
			$registry,
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
		);
	}

	/**
	 * Build the real production Migration lifecycle.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function lifecycle( wpdb $database ): MigrationLifecycle {
		$registry = ( new MigrationRegistryFactory( $database ) )->create();
		$store    = new WordPressSchemaVersionStore();

		return new MigrationLifecycle(
			RETURNTAG_TAGCORE_FILE,
			new MigrationRunner(
				$registry,
				$store,
				new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
			),
			new SchemaState( $store, $registry )
		);
	}

	/**
	 * Insert one non-PII predecessor record.
	 *
	 * @param wpdb   $database WordPress database adapter.
	 * @param string $batch_code Synthetic Batch code.
	 */
	private function insert_batch_fixture( wpdb $database, string $batch_code ): void {
		$result = $database->insert(
			( new TableNames( $database->prefix ) )->batches(),
			array(
				'batch_code'         => $batch_code,
				'tag_type'           => 'classic_tag',
				'model_code'         => 'RT110-MODEL',
				'smart_network'      => 'none',
				'manufacturer'       => 'Synthetic Manufacturer',
				'sales_channel'      => 'direct',
				'requested_quantity' => 1,
				'generated_quantity' => 0,
				'batch_status'       => 'draft',
				'activation_enabled' => 0,
				'notes'              => 'No production data.',
				'created_by'         => 1,
				'created_at'         => '2026-07-24 00:00:00',
				'updated_at'         => '2026-07-24 00:00:00',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		self::assertSame( 1, $result );
	}

	/**
	 * Return every trusted phase-one table name in lexical order.
	 *
	 * @param wpdb $database WordPress database adapter.
	 * @return list<string>
	 */
	private function table_names( wpdb $database ): array {
		$names  = new TableNames( $database->prefix );
		$tables = array(
			$names->access_tokens(),
			$names->auth_challenges(),
			$names->batch_exports(),
			$names->batches(),
			$names->conversations(),
			$names->events(),
			$names->messages(),
			$names->tags(),
		);
		sort( $tables );

		return $tables;
	}

	/**
	 * Read existing ReturnTag tables for the active prefix.
	 *
	 * @param wpdb $database WordPress database adapter.
	 * @return list<string>
	 */
	private function existing_returntag_tables( wpdb $database ): array {
		$like   = $database->esc_like( $database->prefix . 'returntag_' ) . '%';
		$rows   = $database->get_col(
			$database->prepare(
				'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE %s ORDER BY TABLE_NAME',
				$like
			)
		);
		$tables = array_values( array_filter( $rows, 'is_string' ) );
		sort( $tables );

		return $tables;
	}

	/**
	 * Remove only trusted ReturnTag tables from the isolated test database.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function clear_schema( wpdb $database ): void {
		$names = new TableNames( $database->prefix );

		foreach ( array( $names->events(), $names->access_tokens(), $names->messages(), $names->conversations(), $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated test cleanup with trusted identifiers.
			$database->query( "DROP TABLE IF EXISTS {$table_name}" );
		}

		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}
}
