<?php
/**
 * WordPress integration tests for RT-103 schema version 0002.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Infrastructure\Migration\CreateBatchesTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\CreateTagsTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationException;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistry;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaInspector;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use WP_UnitTestCase;
use wpdb;

/**
 * Verifies fresh install, upgrade, drift, retry, and the exact Tags schema.
 */
final class TagsMigrationTest extends WP_UnitTestCase {
	/**
	 * Physical batches table in the isolated WordPress test database.
	 *
	 * @var string
	 */
	private string $batches_table;

	/**
	 * Physical tags table in the isolated WordPress test database.
	 *
	 * @var string
	 */
	private string $tags_table;

	/**
	 * Reset the RT-103 isolated schema fixture before each test.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();

		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$table_names         = new TableNames( $wpdb->prefix );
		$this->batches_table = $table_names->batches();
		$this->tags_table    = $table_names->tags();

		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
		$this->drop_table( $wpdb, $this->tags_table );
		$this->drop_table( $wpdb, $this->batches_table );
	}

	/**
	 * Remove only isolated RT-103 fixtures after each test.
	 */
	protected function tearDown(): void {
		global $wpdb;

		$this->drop_table( $wpdb, $this->tags_table );
		$this->drop_table( $wpdb, $this->batches_table );
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );

		parent::tearDown();
	}

	/**
	 * The isolated RT-103 chain must register contiguous versions in order.
	 */
	public function test_rt103_registry_registers_versions_one_and_two_in_order(): void {

		$registry   = $this->registry();
		$migrations = $registry->all();
		self::assertSame( 2, $registry->target_version() );
		self::assertCount( 2, $migrations );
		self::assertInstanceOf( CreateBatchesTableMigration::class, $migrations[0] );
		self::assertInstanceOf( CreateTagsTableMigration::class, $migrations[1] );
		self::assertSame( array( 1, 2 ), array_map( static fn( $migration ): int => $migration->version(), $migrations ) );
	}

	/**
	 * Fresh installation must execute the complete production chain from zero.
	 */
	public function test_fresh_install_advances_schema_from_zero_to_two(): void {
		$report = $this->runner()->migrate();

		self::assertSame( 0, $report->starting_version );
		self::assertSame( 2, $report->ending_version );
		self::assertSame( array( 1, 2 ), $report->applied_versions );
		self::assertSame( 2, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
		self::assertTrue( $this->batches_migration()->verify() );
		self::assertTrue( $this->tags_migration()->verify() );
	}

	/**
	 * Upgrade from version one must preserve the existing batches table and row.
	 */
	public function test_upgrade_from_one_to_two_preserves_batches_schema_and_data(): void {
		global $wpdb;

		$this->batches_migration()->up();
		self::assertTrue( $this->batches_migration()->verify() );
		self::assertSame( 1, $this->insert_batch( $wpdb, $this->batches_table, 'RT-103-UPGRADE' ) );
		$this->set_schema_version( 1 );

		$before = $this->show_create_table( $wpdb, $this->batches_table );
		$report = $this->runner()->migrate();

		self::assertSame( 1, $report->starting_version );
		self::assertSame( 2, $report->ending_version );
		self::assertSame( array( 2 ), $report->applied_versions );
		self::assertSame( $before, $this->show_create_table( $wpdb, $this->batches_table ) );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier with a prepared value placeholder.
		$query = $wpdb->prepare( "SELECT COUNT(*) FROM {$this->batches_table} WHERE batch_code = %s", 'RT-103-UPGRADE' );
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query was prepared immediately above.
		self::assertSame( 1, (int) $wpdb->get_var( $query ) );
		self::assertTrue( $this->tags_migration()->verify() );
	}

	/**
	 * Complete schema and direct dbDelta execution must remain idempotent.
	 */
	public function test_complete_schema_is_idempotent(): void {
		global $wpdb;

		$runner = $this->runner();
		$runner->migrate();
		$before = $this->show_create_table( $wpdb, $this->tags_table );

		$this->tags_migration()->up();

		self::assertSame( $before, $this->show_create_table( $wpdb, $this->tags_table ) );
		self::assertTrue( $this->tags_migration()->verify() );

		$report = $runner->migrate();

		self::assertSame( 2, $report->starting_version );
		self::assertSame( 2, $report->ending_version );
		self::assertSame( array(), $report->applied_versions );
	}

	/**
	 * A safely repairable missing index must be restored on version-two retry.
	 */
	public function test_retry_repairs_missing_tags_index(): void {
		global $wpdb;

		$this->runner()->migrate();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted test table in the isolated integration database.
		$wpdb->query( "ALTER TABLE {$this->tags_table} DROP INDEX tag_status_updated_at" );
		$this->set_schema_version( 1 );

		self::assertFalse( $this->tags_migration()->verify() );

		$report = $this->runner()->migrate();

		self::assertSame( array( 2 ), $report->applied_versions );
		self::assertSame( 2, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
		self::assertTrue( $this->tags_migration()->verify() );
	}

	/**
	 * An unsafe Tags engine mismatch must leave the last verified version at one.
	 */
	public function test_wrong_tags_engine_fails_without_advancing_version(): void {
		global $wpdb;

		$this->runner()->migrate();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Deliberate isolated failure fixture.
		$wpdb->query( "ALTER TABLE {$this->tags_table} ENGINE=MyISAM" );
		$this->set_schema_version( 1 );

		try {
			$this->runner()->migrate();
			self::fail( 'Expected the unsafe Tags engine mismatch to fail verification.' );
		} catch ( MigrationException ) {
			self::assertSame( 1, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
			self::assertFalse( $this->tags_migration()->verify() );
		}
	}

	/**
	 * An incompatible Tags default must be preserved and block version two.
	 */
	public function test_incompatible_tags_default_is_not_auto_converted(): void {
		global $wpdb;

		$this->runner()->migrate();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Deliberate isolated drift fixture.
		$wpdb->query( "ALTER TABLE {$this->tags_table} ALTER COLUMN tag_status SET DEFAULT 'active'" );
		$this->set_schema_version( 1 );

		try {
			$this->runner()->migrate();
			self::fail( 'Expected incompatible Tags default drift to block dbDelta.' );
		} catch ( MigrationException ) {
			self::assertSame( 1, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
			self::assertSame( 'active', $this->column_default( $wpdb, $this->tags_table, 'tag_status' ) );
		}
	}

	/**
	 * Version two must fail closed when the recorded predecessor has drifted.
	 */
	public function test_missing_batches_predecessor_blocks_version_two(): void {
		global $wpdb;

		$this->batches_migration()->up();
		self::assertTrue( $this->batches_migration()->verify() );
		$this->set_schema_version( 1 );
		$this->drop_table( $wpdb, $this->batches_table );

		try {
			$this->runner()->migrate();
			self::fail( 'Expected predecessor drift to block Migration 0002.' );
		} catch ( MigrationException ) {
			self::assertSame( 1, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
			self::assertFalse( $this->table_exists( $wpdb, $this->tags_table ) );
		}
	}

	/**
	 * Defaults and the public Tag ID primary key must match the storage contract.
	 */
	public function test_defaults_and_duplicate_tag_id_constraint(): void {
		global $wpdb;

		$this->runner()->migrate();
		self::assertSame( 1, $this->insert_batch( $wpdb, $this->batches_table, 'RT-103-DEFAULTS' ) );
		self::assertSame( 1, $this->insert_tag( $wpdb, $this->tags_table, 'A7R2W9', 1 ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier with a prepared value placeholder.
		$query = $wpdb->prepare( "SELECT owner_id, model_code, item_name, public_label, tag_status, lost_mode, lost_message, owner_pairing_ack_at, activated_at, owner_changed_at, last_scanned_at FROM {$this->tags_table} WHERE tag_id = %s", 'A7R2W9' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query was prepared immediately above.
		$row = $wpdb->get_row( $query, ARRAY_A );

		self::assertIsArray( $row );
		self::assertNull( $row['owner_id'] );
		self::assertNull( $row['model_code'] );
		self::assertNull( $row['item_name'] );
		self::assertNull( $row['public_label'] );
		self::assertSame( 'unregistered', $row['tag_status'] );
		self::assertSame( '0', $row['lost_mode'] );
		self::assertNull( $row['lost_message'] );
		self::assertNull( $row['owner_pairing_ack_at'] );
		self::assertNull( $row['activated_at'] );
		self::assertNull( $row['owner_changed_at'] );
		self::assertNull( $row['last_scanned_at'] );

		$previous_suppression = $wpdb->suppress_errors( true );

		try {
			self::assertFalse( $this->insert_tag( $wpdb, $this->tags_table, 'A7R2W9', 1 ) );
		} finally {
			$wpdb->suppress_errors( $previous_suppression );
		}
	}

	/**
	 * Raw metadata must independently match the exact RT-103 contract.
	 */
	public function test_physical_tags_schema_matches_the_independent_contract(): void {
		global $wpdb;

		$this->runner()->migrate();
		$this->assert_table_contract( $wpdb );
	}

	/**
	 * Production migration SQL must honor a non-default WordPress prefix.
	 */
	public function test_migration_supports_non_default_table_prefix(): void {
		$database = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$result   = $database->set_prefix( 'rt103_' );
		$names    = new TableNames( $database->prefix );

		self::assertNotWPError( $result );
		self::assertSame( 'rt103_returntag_tags', $names->tags() );

		$this->drop_table( $database, $names->tags() );
		$this->drop_table( $database, $names->batches() );
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );

		try {
			$registry   = $this->registry( $database );
				$runner = new MigrationRunner(
					$registry,
					new WordPressSchemaVersionStore(),
					new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
				);

			self::assertSame( 2, $runner->migrate()->ending_version );
			self::assertTrue( $registry->all()[1]->verify() );
		} finally {
			$this->drop_table( $database, $names->tags() );
			$this->drop_table( $database, $names->batches() );
			delete_option( WordPressSchemaVersionStore::OPTION_NAME );
		}
	}

	/**
	 * Build an RT-103-only Registry and Runner against the isolated database.
	 */
	private function runner(): MigrationRunner {
		global $wpdb;

		return new MigrationRunner(
			$this->registry( $wpdb ),
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $wpdb, get_current_blog_id(), 0 )
		);
	}

	/**
	 * Return an RT-103-only Migration registry.
	 *
	 * @param wpdb|null $database Optional WordPress database adapter.
	 */
	private function registry( ?wpdb $database = null ): MigrationRegistry {

		global $wpdb;

		$active_database = $database ?? $wpdb;
		$table_names     = new TableNames( $active_database->prefix );
		$inspector       = new WordPressSchemaInspector( $active_database );
		$batches         = new CreateBatchesTableMigration( $active_database, $table_names, $inspector );
		$tags            = new CreateTagsTableMigration( $active_database, $table_names, $inspector, $batches );

		return new MigrationRegistry( array( $batches, $tags ) );
	}
	/**
	 * Return version 0001 from production composition.
	 */
	private function batches_migration(): CreateBatchesTableMigration {
		$migration = $this->registry()->all()[0];
		self::assertInstanceOf( CreateBatchesTableMigration::class, $migration );

		return $migration;
	}

	/**
	 * Return version 0002 from production composition.
	 */
	private function tags_migration(): CreateTagsTableMigration {
		$migration = $this->registry()->all()[1];
		self::assertInstanceOf( CreateTagsTableMigration::class, $migration );

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
	 * Insert one valid batches fixture.
	 *
	 * @param wpdb   $database   WordPress database adapter.
	 * @param string $table_name Trusted table name.
	 * @param string $batch_code Fixture batch code.
	 * @return int|false Number of inserted rows or false.
	 */
	private function insert_batch( wpdb $database, string $table_name, string $batch_code ): int|false {
		return $database->insert(
			$table_name,
			array(
				'batch_code'         => $batch_code,
				'tag_type'           => 'sticker',
				'requested_quantity' => 1,
				'created_by'         => 1,
				'created_at'         => '2026-07-22 00:00:00',
				'updated_at'         => '2026-07-22 00:00:00',
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Insert one minimal valid Tags fixture and allow database defaults to run.
	 *
	 * @param wpdb   $database   WordPress database adapter.
	 * @param string $table_name Trusted table name.
	 * @param string $tag_id     Canonical public Tag ID fixture.
	 * @param int    $batch_id   Existing batch identifier.
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
	 * Assert table, columns, indexes, and absence of foreign keys independently.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function assert_table_contract( wpdb $database ): void {
		$database_name = $database->get_var( 'SELECT DATABASE()' );
		self::assertIsString( $database_name );

		$table_query = $database->prepare(
			'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
			$database_name,
			$this->tags_table
		);
		$table       = $database->get_row( $table_query, ARRAY_A );

		self::assertIsArray( $table );
		self::assertSame( 'InnoDB', $table['ENGINE'] );
		self::assertSame( $database->collate, $table['TABLE_COLLATION'] );

		$column_query = $database->prepare(
			'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_MAXIMUM_LENGTH, CHARACTER_SET_NAME, COLLATION_NAME, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s ORDER BY ORDINAL_POSITION',
			$database_name,
			$this->tags_table
		);
		$rows         = $database->get_results( $column_query, ARRAY_A );
		$columns      = array();

		self::assertIsArray( $rows );

		foreach ( $rows as $row ) {
			$columns[ (string) $row['COLUMN_NAME'] ] = array(
				'data_type'      => strtolower( (string) $row['DATA_TYPE'] ),
				'unsigned'       => str_contains( strtolower( (string) $row['COLUMN_TYPE'] ), 'unsigned' ),
				'nullable'       => 'YES' === $row['IS_NULLABLE'],
				'default'        => $this->normalize_metadata_default( $row['COLUMN_DEFAULT'] ),
				'maximum_length' => isset( $row['CHARACTER_MAXIMUM_LENGTH'] ) ? (int) $row['CHARACTER_MAXIMUM_LENGTH'] : null,
				'character_set'  => is_string( $row['CHARACTER_SET_NAME'] ) ? strtolower( $row['CHARACTER_SET_NAME'] ) : null,
				'collation'      => is_string( $row['COLLATION_NAME'] ) ? strtolower( $row['COLLATION_NAME'] ) : null,
				'auto_increment' => str_contains( strtolower( (string) $row['EXTRA'] ), 'auto_increment' ),
			);
		}

		$unicode = strtolower( (string) $database->charset );
		$collate = strtolower( (string) $database->collate );
		self::assertSame(
			array(
				'tag_id'               => $this->metadata_column( 'char', false, false, null, 6, 'ascii', 'ascii_bin' ),
				'batch_id'             => $this->metadata_column( 'bigint', true, false ),
				'owner_id'             => $this->metadata_column( 'bigint', true, true ),
				'tag_type'             => $this->metadata_column( 'varchar', false, false, null, 32, 'ascii', 'ascii_bin' ),
				'model_code'           => $this->metadata_column( 'varchar', false, true, null, 191, 'ascii', 'ascii_bin' ),
				'item_name'            => $this->metadata_column( 'varchar', false, true, null, 191, $unicode, $collate ),
				'public_label'         => $this->metadata_column( 'varchar', false, true, null, 191, $unicode, $collate ),
				'tag_status'           => $this->metadata_column( 'varchar', false, false, 'unregistered', 32, 'ascii', 'ascii_bin' ),
				'lost_mode'            => $this->metadata_column( 'tinyint', true, false, '0' ),
				'lost_message'         => $this->metadata_column( 'text', false, true, null, 65535, $unicode, $collate ),
				'owner_pairing_ack_at' => $this->metadata_column( 'datetime', false, true ),
				'activated_at'         => $this->metadata_column( 'datetime', false, true ),
				'owner_changed_at'     => $this->metadata_column( 'datetime', false, true ),
				'last_scanned_at'      => $this->metadata_column( 'datetime', false, true ),
				'created_at'           => $this->metadata_column( 'datetime', false, false ),
				'updated_at'           => $this->metadata_column( 'datetime', false, false ),
			),
			$columns
		);

		$index_query = $database->prepare(
			'SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s ORDER BY INDEX_NAME, SEQ_IN_INDEX',
			$database_name,
			$this->tags_table
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
				'PRIMARY'               => array(
					'unique'  => true,
					'columns' => array( 'tag_id' ),
				),
				'batch_id_status'       => array(
					'unique'  => false,
					'columns' => array( 'batch_id', 'tag_status' ),
				),
				'owner_id_status'       => array(
					'unique'  => false,
					'columns' => array( 'owner_id', 'tag_status' ),
				),
				'tag_status_updated_at' => array(
					'unique'  => false,
					'columns' => array( 'tag_status', 'updated_at' ),
				),
			),
			$indexes
		);

		$foreign_key_query = $database->prepare(
			'SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = %s AND TABLE_NAME = %s',
			$database_name,
			$this->tags_table
		);
		self::assertSame( array(), $database->get_col( $foreign_key_query ) );
	}

	/**
	 * Build one normalized independent column expectation.
	 *
	 * @param string      $data_type      SQL data type.
	 * @param bool        $unsigned       Whether the integer is unsigned.
	 * @param bool        $nullable       Whether NULL is allowed.
	 * @param string|null $default_value  Normalized default value.
	 * @param int|null    $maximum_length Character length where applicable.
	 * @param string|null $character_set  Character set where applicable.
	 * @param string|null $collation      Collation where applicable.
	 * @return array{data_type: string, unsigned: bool, nullable: bool, default: string|null, maximum_length: int|null, character_set: string|null, collation: string|null, auto_increment: false}
	 */
	private function metadata_column(
		string $data_type,
		bool $unsigned,
		bool $nullable,
		?string $default_value = null,
		?int $maximum_length = null,
		?string $character_set = null,
		?string $collation = null
	): array {
		return array(
			'data_type'      => $data_type,
			'unsigned'       => $unsigned,
			'nullable'       => $nullable,
			'default'        => $default_value,
			'maximum_length' => $maximum_length,
			'character_set'  => $character_set,
			'collation'      => $collation,
			'auto_increment' => false,
		);
	}

	/**
	 * Normalize MySQL and MariaDB information-schema default representations.
	 *
	 * @param mixed $value Raw COLUMN_DEFAULT value.
	 */
	private function normalize_metadata_default( mixed $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$normalized = (string) $value;

		if ( 'NULL' === strtoupper( $normalized ) ) {
			return null;
		}

		if ( 2 <= strlen( $normalized ) && "'" === $normalized[0] && "'" === $normalized[ strlen( $normalized ) - 1 ] ) {
			$normalized = str_replace( "''", "'", substr( $normalized, 1, -1 ) );
		}

		return $normalized;
	}

	/**
	 * Capture a trusted table definition for preservation and idempotency checks.
	 *
	 * @param wpdb   $database   WordPress database adapter.
	 * @param string $table_name Trusted table name.
	 */
	private function show_create_table( wpdb $database, string $table_name ): string {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted isolated test table identifier.
		$row = $database->get_row( "SHOW CREATE TABLE {$table_name}", ARRAY_N );
		self::assertIsArray( $row );
		self::assertIsString( $row[1] ?? null );

		return $row[1];
	}

	/**
	 * Determine whether one trusted table exists in the active database.
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
	 * Read one trusted column default from the isolated schema fixture.
	 *
	 * @param wpdb   $database    WordPress database adapter.
	 * @param string $table_name  Trusted table name.
	 * @param string $column_name Trusted column name.
	 */
	private function column_default( wpdb $database, string $table_name, string $column_name ): ?string {
		$database_name = $database->get_var( 'SELECT DATABASE()' );

		if ( ! is_string( $database_name ) ) {
			return null;
		}

		$query = $database->prepare(
			'SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
			$database_name,
			$table_name,
			$column_name
		);
		$value = $database->get_var( $query );

		return is_string( $value ) ? $this->normalize_metadata_default( $value ) : null;
	}

	/**
	 * Drop only a trusted table in the isolated integration database.
	 *
	 * @param wpdb   $database   WordPress database adapter.
	 * @param string $table_name Trusted table name.
	 */
	private function drop_table( wpdb $database, string $table_name ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated integration-test cleanup for a trusted table.
		$database->query( "DROP TABLE IF EXISTS {$table_name}" );
	}
}
