<?php
/**
 * WordPress integration tests for RT-105 schema version 0004.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Infrastructure\Migration\CreateAuthChallengesTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\CreateBatchExportsTableMigration;
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
 * Verifies fresh install, upgrade, drift, retry, and privacy-safe storage.
 */
final class AuthChallengesMigrationTest extends WP_UnitTestCase {
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
	 * Physical Auth Challenges table in the isolated test database.
	 *
	 * @var string
	 */
	private string $challenges_table;

	/**
	 * Reset the isolated RT-105 schema before each test.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();

		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$names                  = new TableNames( $wpdb->prefix );
		$this->batches_table    = $names->batches();
		$this->tags_table       = $names->tags();
		$this->exports_table    = $names->batch_exports();
		$this->challenges_table = $names->auth_challenges();

		$this->clear_schema( $wpdb, $names );
	}

	/**
	 * Remove only isolated RT-105 fixtures after each test.
	 */
	protected function tearDown(): void {
		global $wpdb;

		$this->clear_schema( $wpdb, new TableNames( $wpdb->prefix ) );

		parent::tearDown();
	}

	/**
	 * The isolated RT-105 registry must contain contiguous versions one to four.
	 */
	public function test_rt105_registry_registers_versions_one_through_four(): void {
		global $wpdb;

		$registry   = $this->rt105_registry( $wpdb );
		$migrations = $registry->all();

		self::assertSame( 4, $registry->target_version() );
		self::assertCount( 4, $migrations );
		self::assertInstanceOf( CreateAuthChallengesTableMigration::class, $migrations[3] );
		self::assertSame( array( 1, 2, 3, 4 ), array_map( static fn( $migration ): int => $migration->version(), $migrations ) );
	}

	/**
	 * Fresh installation must apply and verify all four schema versions.
	 */
	public function test_fresh_install_advances_schema_from_zero_to_four(): void {
		global $wpdb;

		$report = $this->rt105_runner( $wpdb )->migrate();

		self::assertSame( 0, $report->starting_version );
		self::assertSame( 4, $report->ending_version );
		self::assertSame( array( 1, 2, 3, 4 ), $report->applied_versions );
		self::assertSame( 4, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
		self::assertTrue( $this->auth_migration( $wpdb )->verify() );
	}

	/**
	 * Upgrade from three must preserve all predecessor definitions and data.
	 */
	public function test_upgrade_from_three_to_four_preserves_predecessor_data(): void {
		global $wpdb;

		$this->rt104_runner( $wpdb )->migrate();
		$batch_id = $this->insert_batch( $wpdb, 'RT-105-UPGRADE' );
		self::assertGreaterThan( 0, $batch_id );
		self::assertSame( 1, $this->insert_tag( $wpdb, 'N7R2W9', $batch_id ) );
		self::assertSame( 1, $this->insert_export( $wpdb, $batch_id ) );

		$batches_before = $this->show_create_table( $wpdb, $this->batches_table );
		$tags_before    = $this->show_create_table( $wpdb, $this->tags_table );
		$exports_before = $this->show_create_table( $wpdb, $this->exports_table );
		$report         = $this->rt105_runner( $wpdb )->migrate();

		self::assertSame( 3, $report->starting_version );
		self::assertSame( 4, $report->ending_version );
		self::assertSame( array( 4 ), $report->applied_versions );
		self::assertSame( $batches_before, $this->show_create_table( $wpdb, $this->batches_table ) );
		self::assertSame( $tags_before, $this->show_create_table( $wpdb, $this->tags_table ) );
		self::assertSame( $exports_before, $this->show_create_table( $wpdb, $this->exports_table ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
		self::assertSame( 1, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->exports_table}" ) );
	}

	/**
	 * Complete schema and direct Migration execution must remain idempotent.
	 */
	public function test_complete_schema_is_idempotent(): void {
		global $wpdb;

		$runner = $this->rt105_runner( $wpdb );
		$runner->migrate();
		$before = $this->show_create_table( $wpdb, $this->challenges_table );

		$this->auth_migration( $wpdb )->up();

		self::assertSame( $before, $this->show_create_table( $wpdb, $this->challenges_table ) );
		$report = $runner->migrate();
		self::assertSame( 4, $report->starting_version );
		self::assertSame( 4, $report->ending_version );
		self::assertSame( array(), $report->applied_versions );
	}

	/**
	 * A missing expected index must be restored during a safe retry.
	 */
	public function test_retry_repairs_missing_expiry_index(): void {
		global $wpdb;

		$this->rt105_runner( $wpdb )->migrate();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Deliberate isolated drift fixture.
		$wpdb->query( "ALTER TABLE {$this->challenges_table} DROP INDEX expires_consumed_at" );
		$this->set_schema_version( 3 );

		$report = $this->rt105_runner( $wpdb )->migrate();

		self::assertSame( array( 4 ), $report->applied_versions );
		self::assertSame( 4, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
		self::assertTrue( $this->auth_migration( $wpdb )->verify() );
	}

	/**
	 * Incompatible sensitive storage must fail before dbDelta can convert it.
	 */
	public function test_incompatible_hash_column_is_preserved_and_version_stays_three(): void {
		global $wpdb;

		$this->rt105_runner( $wpdb )->migrate();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Deliberate isolated drift fixture.
		$wpdb->query( "ALTER TABLE {$this->challenges_table} MODIFY email_lookup char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL" );
		$this->set_schema_version( 3 );

		try {
			$this->rt105_runner( $wpdb )->migrate();
			self::fail( 'Expected incompatible challenge schema to block dbDelta.' );
		} catch ( MigrationException ) {
			self::assertSame( 3, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
			self::assertSame( 'char(32)', $this->column_type( $wpdb, 'email_lookup' ) );
		}
	}

	/**
	 * Version four must fail closed when its export predecessor is unavailable.
	 */
	public function test_missing_exports_predecessor_blocks_challenges_creation(): void {
		global $wpdb;

		$this->rt104_runner( $wpdb )->migrate();
		$this->drop_table( $wpdb, $this->exports_table );

		try {
			$this->rt105_runner( $wpdb )->migrate();
			self::fail( 'Expected predecessor drift to block Migration 0004.' );
		} catch ( MigrationException ) {
			self::assertSame( 3, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
			self::assertFalse( $this->table_exists( $wpdb, $this->challenges_table ) );
		}
	}

	/**
	 * Privacy-safe values must round trip without imposing false uniqueness.
	 */
	public function test_binary_ciphertext_defaults_and_repeat_challenges_are_supported(): void {
		global $wpdb;

		$this->rt105_runner( $wpdb )->migrate();
		$ciphertext = "\x00\x01fixture-ciphertext\xff";
		$lookup     = str_repeat( 'a', 64 );

		self::assertSame( 1, $this->insert_challenge( $wpdb, $ciphertext, $lookup ) );
		self::assertSame( 1, $this->insert_challenge( $wpdb, "\x02second-envelope", $lookup ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier with no external input.
		$row = $wpdb->get_row( "SELECT * FROM {$this->challenges_table} ORDER BY challenge_id ASC LIMIT 1", ARRAY_A );
		self::assertIsArray( $row );
		self::assertSame( $ciphertext, $row['email_ciphertext'] );
		self::assertSame( 0, (int) $row['attempt_count'] );
		self::assertSame( 0, (int) $row['send_count'] );
		self::assertNull( $row['ip_hash'] );
		self::assertNull( $row['verified_at'] );
		self::assertNull( $row['consumed_at'] );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier with a prepared value placeholder.
		$query = $wpdb->prepare( "SELECT COUNT(*) FROM {$this->challenges_table} WHERE email_lookup = %s", strtoupper( $lookup ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query was prepared immediately above.
		self::assertSame( 0, (int) $wpdb->get_var( $query ) );
	}

	/**
	 * Raw metadata must independently match the exact RT-105 contract.
	 */
	public function test_physical_schema_matches_independent_contract(): void {
		global $wpdb;

		$this->rt105_runner( $wpdb )->migrate();
		$this->assert_table_contract( $wpdb, $this->challenges_table );
	}

	/**
	 * Migration SQL must honor a non-default WordPress prefix.
	 */
	public function test_migration_supports_non_default_table_prefix(): void {
		$database = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$result   = $database->set_prefix( 'rt105_' );
		$names    = new TableNames( $database->prefix );

		self::assertNotWPError( $result );
		self::assertSame( 'rt105_returntag_auth_challenges', $names->auth_challenges() );
		$this->clear_schema( $database, $names );

		try {
			$registry = $this->rt105_registry( $database );
			$runner   = new MigrationRunner(
				$registry,
				new WordPressSchemaVersionStore(),
				new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
			);

			self::assertSame( 4, $runner->migrate()->ending_version );
			self::assertTrue( $registry->all()[3]->verify() );
		} finally {
			$this->clear_schema( $database, $names );
		}
	}

	/**
	 * Build the isolated RT-105 registry.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function rt105_registry( wpdb $database ): MigrationRegistry {
		$rt104      = $this->rt104_registry( $database );
		$migrations = $rt104->all();
		$names      = new TableNames( $database->prefix );
		$inspector  = new WordPressSchemaInspector( $database );
		$exports    = $migrations[2];
		self::assertInstanceOf( CreateBatchExportsTableMigration::class, $exports );

		$migrations[] = new CreateAuthChallengesTableMigration( $database, $names, $inspector, $exports );

		return new MigrationRegistry( $migrations );
	}

	/**
	 * Build the isolated RT-104 registry.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function rt104_registry( wpdb $database ): MigrationRegistry {
		$names     = new TableNames( $database->prefix );
		$inspector = new WordPressSchemaInspector( $database );
		$batches   = new CreateBatchesTableMigration( $database, $names, $inspector );
		$tags      = new CreateTagsTableMigration( $database, $names, $inspector, $batches );
		$exports   = new CreateBatchExportsTableMigration( $database, $names, $inspector, $tags );

		return new MigrationRegistry( array( $batches, $tags, $exports ) );
	}

	/**
	 * Build the isolated RT-105 Runner.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function rt105_runner( wpdb $database ): MigrationRunner {
		return new MigrationRunner(
			$this->rt105_registry( $database ),
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
		);
	}

	/**
	 * Build the isolated RT-104 Runner.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function rt104_runner( wpdb $database ): MigrationRunner {
		return new MigrationRunner(
			$this->rt104_registry( $database ),
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
		);
	}

	/**
	 * Return version 0004 from the isolated RT-105 composition.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function auth_migration( wpdb $database ): CreateAuthChallengesTableMigration {
		$migration = $this->rt105_registry( $database )->all()[3];
		self::assertInstanceOf( CreateAuthChallengesTableMigration::class, $migration );

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
	 * @param string $batch_code Fixture Batch code.
	 */
	private function insert_batch( wpdb $database, string $batch_code ): int {
		$result = $database->insert(
			$this->batches_table,
			array(
				'batch_code'         => $batch_code,
				'tag_type'           => 'sticker',
				'requested_quantity' => 1,
				'created_by'         => 1,
				'created_at'         => '2026-07-23 00:00:00',
				'updated_at'         => '2026-07-23 00:00:00',
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return false === $result ? 0 : (int) $database->insert_id;
	}

	/**
	 * Insert one minimal Tag fixture.
	 *
	 * @param wpdb   $database WordPress database adapter.
	 * @param string $tag_id   Public Tag ID fixture.
	 * @param int    $batch_id Existing Batch ID.
	 * @return int|false Number of inserted rows or false.
	 */
	private function insert_tag( wpdb $database, string $tag_id, int $batch_id ): int|false {
		return $database->insert(
			$this->tags_table,
			array(
				'tag_id'     => $tag_id,
				'batch_id'   => $batch_id,
				'tag_type'   => 'sticker',
				'created_at' => '2026-07-23 00:00:00',
				'updated_at' => '2026-07-23 00:00:00',
			),
			array( '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Insert one minimal export audit fixture.
	 *
	 * @param wpdb $database WordPress database adapter.
	 * @param int  $batch_id Existing Batch ID.
	 * @return int|false Number of inserted rows or false.
	 */
	private function insert_export( wpdb $database, int $batch_id ): int|false {
		return $database->insert(
			$this->exports_table,
			array(
				'batch_id'       => $batch_id,
				'export_version' => 1,
				'row_count'      => 1,
				'file_format'    => 'csv',
				'file_checksum'  => str_repeat( 'b', 64 ),
				'created_by'     => 1,
				'created_at'     => '2026-07-23 00:00:00',
			),
			array( '%d', '%d', '%d', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Insert a challenge using only opaque test fixtures.
	 *
	 * @param wpdb   $database   WordPress database adapter.
	 * @param string $ciphertext Opaque binary envelope fixture.
	 * @param string $lookup     HMAC-shaped lookup fixture.
	 * @return int|false Number of inserted rows or false.
	 */
	private function insert_challenge( wpdb $database, string $ciphertext, string $lookup ): int|false {
		return $database->insert(
			$this->challenges_table,
			array(
				'purpose'          => 'passwordless_login',
				'subject_type'     => 'email_identity',
				'subject_id'       => 'fixture-subject',
				'email_ciphertext' => $ciphertext,
				'email_lookup'     => $lookup,
				'code_hash'        => '$fixture$' . str_repeat( 'c', 56 ),
				'expires_at'       => '2026-07-23 00:10:00',
				'created_at'       => '2026-07-23 00:00:00',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
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
				'default'        => $this->normalize_metadata_default( $row['COLUMN_DEFAULT'] ),
				'maximum_length' => isset( $row['CHARACTER_MAXIMUM_LENGTH'] ) ? (int) $row['CHARACTER_MAXIMUM_LENGTH'] : null,
				'character_set'  => is_string( $row['CHARACTER_SET_NAME'] ) ? strtolower( $row['CHARACTER_SET_NAME'] ) : null,
				'collation'      => is_string( $row['COLLATION_NAME'] ) ? strtolower( $row['COLLATION_NAME'] ) : null,
				'auto_increment' => str_contains( strtolower( (string) $row['EXTRA'] ), 'auto_increment' ),
			);
		}

		self::assertSame(
			array(
				'challenge_id'     => $this->metadata_column( 'bigint', true, false, null, null, null, null, true ),
				'purpose'          => $this->metadata_column( 'varchar', false, false, null, 32, 'ascii', 'ascii_bin' ),
				'subject_type'     => $this->metadata_column( 'varchar', false, false, null, 32, 'ascii', 'ascii_bin' ),
				'subject_id'       => $this->metadata_column( 'varchar', false, false, null, 191, 'ascii', 'ascii_bin' ),
				'email_ciphertext' => $this->metadata_column( 'longblob', false, false, null, 4294967295 ),
				'email_lookup'     => $this->metadata_column( 'char', false, false, null, 64, 'ascii', 'ascii_bin' ),
				'code_hash'        => $this->metadata_column( 'varchar', false, false, null, 255, 'ascii', 'ascii_bin' ),
				'attempt_count'    => $this->metadata_column( 'int', true, false, '0' ),
				'send_count'       => $this->metadata_column( 'int', true, false, '0' ),
				'ip_hash'          => $this->metadata_column( 'char', false, true, null, 64, 'ascii', 'ascii_bin' ),
				'expires_at'       => $this->metadata_column( 'datetime' ),
				'verified_at'      => $this->metadata_column( 'datetime', false, true ),
				'consumed_at'      => $this->metadata_column( 'datetime', false, true ),
				'created_at'       => $this->metadata_column( 'datetime' ),
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
				'PRIMARY'                  => array(
					'unique'  => true,
					'columns' => array( 'challenge_id' ),
				),
				'expires_consumed_at'      => array(
					'unique'  => false,
					'columns' => array( 'expires_at', 'consumed_at' ),
				),
				'purpose_email_created_at' => array(
					'unique'  => false,
					'columns' => array( 'purpose', 'email_lookup', 'created_at' ),
				),
				'subject_created_at'       => array(
					'unique'  => false,
					'columns' => array( 'subject_type', 'subject_id', 'created_at' ),
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
		self::assertSame(
			array(),
			array_intersect( array( 'email', 'otp', 'code', 'ip_address' ), array_keys( $columns ) )
		);
	}

	/**
	 * Build one normalized metadata expectation.
	 *
	 * @param string          $data_type      SQL data type.
	 * @param bool            $unsigned       Whether the integer is unsigned.
	 * @param bool            $nullable       Whether NULL is accepted.
	 * @param int|string|null $default_value  Exact database default.
	 * @param int|null        $maximum_length Character or binary length.
	 * @param string|null     $character_set  Character set.
	 * @param string|null     $collation      Collation.
	 * @param bool            $auto_increment Whether the column auto-increments.
	 * @return array{data_type: string, unsigned: bool, nullable: bool, default: int|string|null, maximum_length: int|null, character_set: string|null, collation: string|null, auto_increment: bool}
	 */
	private function metadata_column(
		string $data_type,
		bool $unsigned = false,
		bool $nullable = false,
		int|string|null $default_value = null,
		?int $maximum_length = null,
		?string $character_set = null,
		?string $collation = null,
		bool $auto_increment = false
	): array {
		return array(
			'data_type'      => $data_type,
			'unsigned'       => $unsigned,
			'nullable'       => $nullable,
			'default'        => $default_value,
			'maximum_length' => $maximum_length,
			'character_set'  => $character_set,
			'collation'      => $collation,
			'auto_increment' => $auto_increment,
		);
	}

	/**
	 * Normalize MySQL and MariaDB representations of explicit DEFAULT NULL.
	 *
	 * @param mixed $value Raw information_schema default value.
	 * @return int|string|null
	 */
	private function normalize_metadata_default( mixed $value ): int|string|null {
		if ( null === $value ) {
			return null;
		}

		if ( is_int( $value ) ) {
			return $value;
		}

		$normalized = (string) $value;

		return 'NULL' === strtoupper( $normalized ) ? null : $normalized;
	}

	/**
	 * Read one trusted column type.
	 *
	 * @param wpdb   $database    WordPress database adapter.
	 * @param string $column_name Trusted column name.
	 */
	private function column_type( wpdb $database, string $column_name ): string {
		$database_name = $database->get_var( 'SELECT DATABASE()' );
		self::assertIsString( $database_name );
		$query = $database->prepare(
			'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
			$database_name,
			$this->challenges_table,
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
	 * Remove only trusted RT-105 tables from the isolated database.
	 *
	 * @param wpdb       $database WordPress database adapter.
	 * @param TableNames $names    Trusted table-name mapping.
	 */
	private function clear_schema( wpdb $database, TableNames $names ): void {
		foreach ( array( $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table_name ) {
			$this->drop_table( $database, $table_name );
		}

		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
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
