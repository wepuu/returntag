<?php
/**
 * WordPress integration tests for RT-104 schema version 0003.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Infrastructure\Migration\CreateBatchExportsTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\CreateBatchesTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\CreateTagsTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationException;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistry;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaInspector;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use WP_UnitTestCase;
use wpdb;

/**
 * Verifies fresh install, upgrade, drift, retry, and export audit constraints.
 */
final class BatchExportsMigrationTest extends WP_UnitTestCase {
	/**
	 * Physical Batches table in the isolated test database.
	 *
	 * @var string
	 */
	private string $batches_table;

	/**
	 * Physical Tags table in the isolated test database.
	 *
	 * @var string
	 */
	private string $tags_table;

	/**
	 * Physical Batch Exports table in the isolated test database.
	 *
	 * @var string
	 */
	private string $exports_table;

	/**
	 * Reset the isolated RT-104 schema before each test.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();

		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$names               = new TableNames( $wpdb->prefix );
		$this->batches_table = $names->batches();
		$this->tags_table    = $names->tags();
		$this->exports_table = $names->batch_exports();

		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
		$this->drop_table( $wpdb, $this->exports_table );
		$this->drop_table( $wpdb, $this->tags_table );
		$this->drop_table( $wpdb, $this->batches_table );
	}

	/**
	 * Remove only isolated RT-104 fixtures after each test.
	 */
	protected function tearDown(): void {
		global $wpdb;

		$this->drop_table( $wpdb, $this->exports_table );
		$this->drop_table( $wpdb, $this->tags_table );
		$this->drop_table( $wpdb, $this->batches_table );
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );

		parent::tearDown();
	}

	/**
	 * Production composition must register versions one through three in order.
	 */
	public function test_production_registry_registers_versions_one_through_three(): void {
		$migrations = $this->production_registry()->all();

		self::assertCount( 3, $migrations );
		self::assertInstanceOf( CreateBatchesTableMigration::class, $migrations[0] );
		self::assertInstanceOf( CreateTagsTableMigration::class, $migrations[1] );
		self::assertInstanceOf( CreateBatchExportsTableMigration::class, $migrations[2] );
		self::assertSame( array( 1, 2, 3 ), array_map( static fn( $migration ): int => $migration->version(), $migrations ) );
	}

	/**
	 * The registered activation hook must execute the current production chain.
	 */
	public function test_plugin_activation_executes_production_chain_to_three(): void {
		do_action( 'activate_' . plugin_basename( RETURNTAG_TAGCORE_FILE ), false );

		self::assertSame( 3, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
		self::assertTrue( $this->exports_migration()->verify() );
	}

	/**
	 * Fresh installation must create and verify all three schema versions.
	 */
	public function test_fresh_install_advances_schema_from_zero_to_three(): void {
		$report = $this->production_runner()->migrate();

		self::assertSame( 0, $report->starting_version );
		self::assertSame( 3, $report->ending_version );
		self::assertSame( array( 1, 2, 3 ), $report->applied_versions );
		self::assertSame( 3, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
		self::assertTrue( $this->exports_migration()->verify() );
	}

	/**
	 * Upgrade from two must preserve Batches and Tags definitions and data.
	 */
	public function test_upgrade_from_two_to_three_preserves_predecessor_data(): void {
		global $wpdb;

		$this->rt103_runner( $wpdb )->migrate();
		$batch_id = $this->insert_batch( $wpdb, $this->batches_table, 'RT-104-UPGRADE' );
		self::assertGreaterThan( 0, $batch_id );
		self::assertSame( 1, $this->insert_tag( $wpdb, $this->tags_table, 'M7R2W9', $batch_id ) );

		$batches_before = $this->show_create_table( $wpdb, $this->batches_table );
		$tags_before    = $this->show_create_table( $wpdb, $this->tags_table );
		$report         = $this->production_runner()->migrate();

		self::assertSame( 2, $report->starting_version );
		self::assertSame( 3, $report->ending_version );
		self::assertSame( array( 3 ), $report->applied_versions );
		self::assertSame( $batches_before, $this->show_create_table( $wpdb, $this->batches_table ) );
		self::assertSame( $tags_before, $this->show_create_table( $wpdb, $this->tags_table ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier with a prepared value placeholder.
		$query = $wpdb->prepare( "SELECT COUNT(*) FROM {$this->tags_table} WHERE tag_id = %s", 'M7R2W9' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query was prepared immediately above.
		self::assertSame( 1, (int) $wpdb->get_var( $query ) );
	}

	/**
	 * A complete table and completed Runner must remain idempotent.
	 */
	public function test_complete_schema_is_idempotent(): void {
		global $wpdb;

		$runner = $this->production_runner();
		$runner->migrate();
		$before = $this->show_create_table( $wpdb, $this->exports_table );

		$this->exports_migration()->up();

		self::assertSame( $before, $this->show_create_table( $wpdb, $this->exports_table ) );
		$report = $runner->migrate();
		self::assertSame( 3, $report->starting_version );
		self::assertSame( 3, $report->ending_version );
		self::assertSame( array(), $report->applied_versions );
	}

	/**
	 * A missing expected index is the only automatically repairable drift.
	 */
	public function test_retry_repairs_missing_checksum_index(): void {
		global $wpdb;

		$this->production_runner()->migrate();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted isolated schema fixture.
		$wpdb->query( "ALTER TABLE {$this->exports_table} DROP INDEX batch_file_checksum" );
		$this->set_schema_version( 2 );

		$report = $this->production_runner()->migrate();

		self::assertSame( array( 3 ), $report->applied_versions );
		self::assertSame( 3, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
		self::assertTrue( $this->exports_migration()->verify() );
	}

	/**
	 * An incompatible audit column must not be auto-converted by dbDelta.
	 */
	public function test_incompatible_column_is_preserved_and_version_stays_two(): void {
		global $wpdb;

		$this->production_runner()->migrate();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Deliberate isolated drift fixture.
		$wpdb->query( "ALTER TABLE {$this->exports_table} MODIFY file_checksum char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL" );
		$this->set_schema_version( 2 );

		try {
			$this->production_runner()->migrate();
			self::fail( 'Expected incompatible export schema to block dbDelta.' );
		} catch ( MigrationException ) {
			self::assertSame( 2, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
			self::assertSame( 'char(32)', $this->column_type( $wpdb, $this->exports_table, 'file_checksum' ) );
		}
	}

	/**
	 * Version three must fail closed when the Tags predecessor is unavailable.
	 */
	public function test_missing_tags_predecessor_blocks_exports_creation(): void {
		global $wpdb;

		$this->rt103_runner( $wpdb )->migrate();
		$this->drop_table( $wpdb, $this->tags_table );

		try {
			$this->production_runner()->migrate();
			self::fail( 'Expected predecessor drift to block Migration 0003.' );
		} catch ( MigrationException ) {
			self::assertSame( 2, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
			self::assertFalse( $this->table_exists( $wpdb, $this->exports_table ) );
		}
	}

	/**
	 * Version uniqueness and repeat-export checksum semantics must be enforced.
	 */
	public function test_batch_version_is_unique_while_checksum_can_repeat(): void {
		global $wpdb;

		$this->production_runner()->migrate();
		$first_batch  = $this->insert_batch( $wpdb, $this->batches_table, 'RT-104-FIRST' );
		$second_batch = $this->insert_batch( $wpdb, $this->batches_table, 'RT-104-SECOND' );
		$checksum     = str_repeat( 'a', 64 );

		self::assertGreaterThan( 0, $first_batch );
		self::assertGreaterThan( 0, $second_batch );
		self::assertSame( 1, $this->insert_export( $wpdb, $first_batch, 1, $checksum ) );
		self::assertSame( 1, $this->insert_export( $wpdb, $first_batch, 2, $checksum ) );
		self::assertSame( 1, $this->insert_export( $wpdb, $second_batch, 1, $checksum ) );

		$previous_suppression = $wpdb->suppress_errors( true );

		try {
			self::assertFalse( $this->insert_export( $wpdb, $first_batch, 1, str_repeat( 'b', 64 ) ) );
		} finally {
			$wpdb->suppress_errors( $previous_suppression );
		}
	}

	/**
	 * Raw metadata must independently match the exact RT-104 contract.
	 */
	public function test_physical_schema_matches_independent_contract(): void {
		global $wpdb;

		$this->production_runner()->migrate();
		$this->assert_table_contract( $wpdb, $this->exports_table );
	}

	/**
	 * Production migration SQL must honor a non-default WordPress prefix.
	 */
	public function test_migration_supports_non_default_table_prefix(): void {
		$database = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$result   = $database->set_prefix( 'rt104_' );
		$names    = new TableNames( $database->prefix );

		self::assertNotWPError( $result );
		self::assertSame( 'rt104_returntag_batch_exports', $names->batch_exports() );

		$this->drop_table( $database, $names->batch_exports() );
		$this->drop_table( $database, $names->tags() );
		$this->drop_table( $database, $names->batches() );
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );

		try {
			$registry = ( new MigrationRegistryFactory( $database ) )->create();
			$runner   = new MigrationRunner(
				$registry,
				new WordPressSchemaVersionStore(),
				new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
			);

			self::assertSame( 3, $runner->migrate()->ending_version );
			self::assertTrue( $registry->all()[2]->verify() );
		} finally {
			$this->drop_table( $database, $names->batch_exports() );
			$this->drop_table( $database, $names->tags() );
			$this->drop_table( $database, $names->batches() );
			delete_option( WordPressSchemaVersionStore::OPTION_NAME );
		}
	}

	/**
	 * Return the production Migration registry.
	 */
	private function production_registry(): MigrationRegistry {
		global $wpdb;

		return ( new MigrationRegistryFactory( $wpdb ) )->create();
	}

	/**
	 * Build the production Runner.
	 */
	private function production_runner(): MigrationRunner {
		global $wpdb;

		return new MigrationRunner(
			$this->production_registry(),
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $wpdb, get_current_blog_id(), 0 )
		);
	}

	/**
	 * Build an isolated version-one-through-two Runner.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function rt103_runner( wpdb $database ): MigrationRunner {
		$names     = new TableNames( $database->prefix );
		$inspector = new WordPressSchemaInspector( $database );
		$batches   = new CreateBatchesTableMigration( $database, $names, $inspector );
		$tags      = new CreateTagsTableMigration( $database, $names, $inspector, $batches );

		return new MigrationRunner(
			new MigrationRegistry( array( $batches, $tags ) ),
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
		);
	}

	/**
	 * Return version 0003 from production composition.
	 */
	private function exports_migration(): CreateBatchExportsTableMigration {
		$migration = $this->production_registry()->all()[2];
		self::assertInstanceOf( CreateBatchExportsTableMigration::class, $migration );

		return $migration;
	}

	/**
	 * Persist an isolated verified starting version.
	 *
	 * @param int $version Schema version fixture.
	 */
	private function set_schema_version( int $version ): void {
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
		( new WordPressSchemaVersionStore() )->mark_applied( $version );
	}

	/**
	 * Insert one minimal Batch and return its generated ID.
	 *
	 * @param wpdb   $database   WordPress database adapter.
	 * @param string $table_name Trusted table name.
	 * @param string $batch_code Fixture batch code.
	 */
	private function insert_batch( wpdb $database, string $table_name, string $batch_code ): int {
		$result = $database->insert(
			$table_name,
			array(
				'batch_code'         => $batch_code,
				'tag_type'           => 'sticker',
				'requested_quantity' => 2,
				'created_by'         => 1,
				'created_at'         => '2026-07-22 00:00:00',
				'updated_at'         => '2026-07-22 00:00:00',
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return false === $result ? 0 : (int) $database->insert_id;
	}

	/**
	 * Insert one minimal Tag fixture.
	 *
	 * @param wpdb   $database   WordPress database adapter.
	 * @param string $table_name Trusted table name.
	 * @param string $tag_id     Public Tag ID fixture.
	 * @param int    $batch_id   Existing Batch ID.
	 * @return int|false Number of inserted rows or false.
	 */
	private function insert_tag( wpdb $database, string $table_name, string $tag_id, int $batch_id ): int|false {
		return $database->insert(
			$table_name,
			array(
				'tag_id'     => $tag_id,
				'batch_id'   => $batch_id,
				'tag_type'   => 'sticker',
				'created_at' => '2026-07-22 00:00:00',
				'updated_at' => '2026-07-22 00:00:00',
			),
			array( '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Append one schema-level export audit fixture.
	 *
	 * @param wpdb   $database       WordPress database adapter.
	 * @param int    $batch_id       Existing Batch ID.
	 * @param int    $export_version Export version fixture.
	 * @param string $checksum       SHA-256-shaped fixture.
	 * @return int|false Number of inserted rows or false.
	 */
	private function insert_export( wpdb $database, int $batch_id, int $export_version, string $checksum ): int|false {
		return $database->insert(
			$this->exports_table,
			array(
				'batch_id'       => $batch_id,
				'export_version' => $export_version,
				'row_count'      => 2,
				'file_format'    => 'csv',
				'file_checksum'  => $checksum,
				'created_by'     => 1,
				'created_at'     => '2026-07-22 00:00:00',
			),
			array( '%d', '%d', '%d', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Assert exact columns, indexes, and absence of physical foreign keys.
	 *
	 * @param wpdb   $database   WordPress database adapter.
	 * @param string $table_name Trusted table name.
	 */
	private function assert_table_contract( wpdb $database, string $table_name ): void {
		$database_name = $database->get_var( 'SELECT DATABASE()' );
		self::assertIsString( $database_name );

		$table_query = $database->prepare(
			'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
			$database_name,
			$table_name
		);
		$table       = $database->get_row( $table_query, ARRAY_A );
		self::assertIsArray( $table );
		self::assertSame( 'InnoDB', $table['ENGINE'] );
		self::assertSame( $database->collate, $table['TABLE_COLLATION'] );

		$column_query = $database->prepare(
			'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_MAXIMUM_LENGTH, CHARACTER_SET_NAME, COLLATION_NAME, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s ORDER BY ORDINAL_POSITION',
			$database_name,
			$table_name
		);
		$rows         = $database->get_results( $column_query, ARRAY_A );
		$columns      = array();

		self::assertIsArray( $rows );

		foreach ( $rows as $row ) {
			$columns[ (string) $row['COLUMN_NAME'] ] = array(
				'data_type'      => strtolower( (string) $row['DATA_TYPE'] ),
				'unsigned'       => str_contains( strtolower( (string) $row['COLUMN_TYPE'] ), 'unsigned' ),
				'nullable'       => 'YES' === $row['IS_NULLABLE'],
				'default'        => $row['COLUMN_DEFAULT'],
				'maximum_length' => isset( $row['CHARACTER_MAXIMUM_LENGTH'] ) ? (int) $row['CHARACTER_MAXIMUM_LENGTH'] : null,
				'character_set'  => is_string( $row['CHARACTER_SET_NAME'] ) ? strtolower( $row['CHARACTER_SET_NAME'] ) : null,
				'collation'      => is_string( $row['COLLATION_NAME'] ) ? strtolower( $row['COLLATION_NAME'] ) : null,
				'auto_increment' => str_contains( strtolower( (string) $row['EXTRA'] ), 'auto_increment' ),
			);
		}

		self::assertSame(
			array(
				'export_id'      => $this->metadata_column( 'bigint', true, null, null, null, true ),
				'batch_id'       => $this->metadata_column( 'bigint', true ),
				'export_version' => $this->metadata_column( 'int', true ),
				'row_count'      => $this->metadata_column( 'int', true ),
				'file_format'    => $this->metadata_column( 'varchar', false, 32, 'ascii', 'ascii_bin' ),
				'file_checksum'  => $this->metadata_column( 'char', false, 64, 'ascii', 'ascii_bin' ),
				'created_by'     => $this->metadata_column( 'bigint', true ),
				'created_at'     => $this->metadata_column( 'datetime', false ),
			),
			$columns
		);

		$index_query = $database->prepare(
			'SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s ORDER BY INDEX_NAME, SEQ_IN_INDEX',
			$database_name,
			$table_name
		);
		$index_rows  = $database->get_results( $index_query, ARRAY_A );
		$indexes     = array();

		self::assertIsArray( $index_rows );

		foreach ( $index_rows as $row ) {
			self::assertNull( $row['SUB_PART'] );
			$name = (string) $row['INDEX_NAME'];

			if ( ! isset( $indexes[ $name ] ) ) {
				$indexes[ $name ] = array(
					'unique'  => '0' === (string) $row['NON_UNIQUE'],
					'columns' => array(),
				);
			}

			$indexes[ $name ]['columns'][] = (string) $row['COLUMN_NAME'];
		}

		ksort( $indexes );
		self::assertSame(
			array(
				'PRIMARY'                     => array(
					'unique'  => true,
					'columns' => array( 'export_id' ),
				),
				'batch_export_version_unique' => array(
					'unique'  => true,
					'columns' => array( 'batch_id', 'export_version' ),
				),
				'batch_file_checksum'         => array(
					'unique'  => false,
					'columns' => array( 'batch_id', 'file_checksum' ),
				),
			),
			$indexes
		);

		$foreign_key_query = $database->prepare(
			'SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = %s AND TABLE_NAME = %s',
			$database_name,
			$table_name
		);
		self::assertSame( array(), $database->get_col( $foreign_key_query ) );
	}

	/**
	 * Build one normalized metadata expectation.
	 *
	 * @param string      $data_type      SQL data type.
	 * @param bool        $unsigned       Whether the integer is unsigned.
	 * @param int|null    $maximum_length Character length.
	 * @param string|null $character_set  Character set.
	 * @param string|null $collation      Collation.
	 * @param bool        $auto_increment Whether the column auto-increments.
	 * @return array{data_type: string, unsigned: bool, nullable: false, default: null, maximum_length: int|null, character_set: string|null, collation: string|null, auto_increment: bool}
	 */
	private function metadata_column(
		string $data_type,
		bool $unsigned,
		?int $maximum_length = null,
		?string $character_set = null,
		?string $collation = null,
		bool $auto_increment = false
	): array {
		return array(
			'data_type'      => $data_type,
			'unsigned'       => $unsigned,
			'nullable'       => false,
			'default'        => null,
			'maximum_length' => $maximum_length,
			'character_set'  => $character_set,
			'collation'      => $collation,
			'auto_increment' => $auto_increment,
		);
	}

	/**
	 * Read one trusted column type.
	 *
	 * @param wpdb   $database    WordPress database adapter.
	 * @param string $table_name  Trusted table name.
	 * @param string $column_name Trusted column name.
	 */
	private function column_type( wpdb $database, string $table_name, string $column_name ): string {
		$database_name = $database->get_var( 'SELECT DATABASE()' );
		self::assertIsString( $database_name );

		$query = $database->prepare(
			'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
			$database_name,
			$table_name,
			$column_name
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query was prepared immediately above.
		$type = $database->get_var( $query );
		self::assertIsString( $type );

		return strtolower( $type );
	}

	/**
	 * Capture one trusted table definition.
	 *
	 * @param wpdb   $database   WordPress database adapter.
	 * @param string $table_name Trusted table name.
	 */
	private function show_create_table( wpdb $database, string $table_name ): string {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted isolated table identifier.
		$row = $database->get_row( "SHOW CREATE TABLE {$table_name}", ARRAY_N );
		self::assertIsArray( $row );
		self::assertIsString( $row[1] ?? null );

		return $row[1];
	}

	/**
	 * Determine whether one trusted table exists.
	 *
	 * @param wpdb   $database   WordPress database adapter.
	 * @param string $table_name Trusted table name.
	 */
	private function table_exists( wpdb $database, string $table_name ): bool {
		$database_name = $database->get_var( 'SELECT DATABASE()' );

		if ( ! is_string( $database_name ) ) {
			return false;
		}

		$query = $database->prepare(
			'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
			$database_name,
			$table_name
		);

		return 1 === (int) $database->get_var( $query );
	}

	/**
	 * Drop only a trusted table in the isolated integration database.
	 *
	 * @param wpdb   $database   WordPress database adapter.
	 * @param string $table_name Trusted table name.
	 */
	private function drop_table( wpdb $database, string $table_name ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated test cleanup for a trusted table.
		$database->query( "DROP TABLE IF EXISTS {$table_name}" );
	}
}
