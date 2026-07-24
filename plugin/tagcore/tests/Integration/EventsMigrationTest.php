<?php
/**
 * WordPress integration tests for RT-108 schema version 0008.
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
 * Verifies fresh install, upgrade, retry, indexing, and privacy-safe storage.
 */
final class EventsMigrationTest extends WP_UnitTestCase {
	/**
	 * Physical Access Tokens table in the isolated test database.
	 *
	 * @var string
	 */
	private string $access_tokens_table;

	/**
	 * Physical Events table in the isolated test database.
	 *
	 * @var string
	 */
	private string $events_table;

	/**
	 * Reset the isolated RT-108 schema before each test.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();

		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$names                     = new TableNames( $wpdb->prefix );
		$this->access_tokens_table = $names->access_tokens();
		$this->events_table        = $names->events();

		$this->clear_schema( $wpdb, $names );
	}

	/**
	 * Remove only isolated RT-108 fixtures after each test.
	 */
	protected function tearDown(): void {
		global $wpdb;

		$this->clear_schema( $wpdb, new TableNames( $wpdb->prefix ) );

		parent::tearDown();
	}

	/**
	 * The isolated registry must contain contiguous versions one through eight.
	 */
	public function test_rt108_registry_registers_versions_one_through_eight(): void {
		global $wpdb;

		$registry   = $this->rt108_registry( $wpdb );
		$migrations = $registry->all();

		self::assertSame( 8, $registry->target_version() );
		self::assertCount( 8, $migrations );
		self::assertInstanceOf( CreateEventsTableMigration::class, $migrations[7] );
		self::assertSame( array( 1, 2, 3, 4, 5, 6, 7, 8 ), array_map( static fn( $migration ): int => $migration->version(), $migrations ) );
	}

	/**
	 * Fresh installation must apply and verify all eight schema versions.
	 */
	public function test_fresh_install_advances_schema_from_zero_to_eight(): void {
		global $wpdb;

		$report = $this->rt108_runner( $wpdb )->migrate();

		self::assertSame( 0, $report->starting_version );
		self::assertSame( 8, $report->ending_version );
		self::assertSame( array( 1, 2, 3, 4, 5, 6, 7, 8 ), $report->applied_versions );
		self::assertSame( 8, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
		self::assertTrue( $this->events_migration( $wpdb )->verify() );
	}

	/**
	 * Upgrade from seven must preserve existing access-token data.
	 */
	public function test_upgrade_from_seven_to_eight_preserves_access_token_data(): void {
		global $wpdb;

		$this->rt107_runner( $wpdb )->migrate();
		$token_hash = str_repeat( 'a', 64 );
		self::assertSame( 1, $this->insert_access_token( $wpdb, $token_hash ) );
		$access_tokens_before = $this->show_create_table( $wpdb, $this->access_tokens_table );

		$report = $this->rt108_runner( $wpdb )->migrate();

		self::assertSame( 7, $report->starting_version );
		self::assertSame( 8, $report->ending_version );
		self::assertSame( array( 8 ), $report->applied_versions );
		self::assertSame( $access_tokens_before, $this->show_create_table( $wpdb, $this->access_tokens_table ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier with no external input.
		self::assertSame( $token_hash, $wpdb->get_var( "SELECT token_hash FROM {$this->access_tokens_table} LIMIT 1" ) );
	}

	/**
	 * Complete schema and direct Migration execution must remain idempotent.
	 */
	public function test_complete_schema_is_idempotent(): void {
		global $wpdb;

		$runner = $this->rt108_runner( $wpdb );
		$runner->migrate();
		$before = $this->show_create_table( $wpdb, $this->events_table );

		$this->events_migration( $wpdb )->up();

		self::assertSame( $before, $this->show_create_table( $wpdb, $this->events_table ) );
		$report = $runner->migrate();
		self::assertSame( 8, $report->starting_version );
		self::assertSame( 8, $report->ending_version );
		self::assertSame( array(), $report->applied_versions );
	}

	/**
	 * A missing global-time index must be restored during a safe retry.
	 */
	public function test_retry_repairs_missing_created_at_index(): void {
		global $wpdb;

		$this->rt108_runner( $wpdb )->migrate();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Deliberate isolated drift fixture.
		$wpdb->query( "ALTER TABLE {$this->events_table} DROP INDEX created_at_event_id" );
		$this->set_schema_version( 7 );

		$report = $this->rt108_runner( $wpdb )->migrate();

		self::assertSame( array( 8 ), $report->applied_versions );
		self::assertTrue( $this->events_migration( $wpdb )->verify() );
	}

	/**
	 * Incompatible metadata storage must fail before dbDelta can convert it.
	 */
	public function test_incompatible_metadata_storage_is_preserved_and_version_stays_seven(): void {
		global $wpdb;

		$this->rt108_runner( $wpdb )->migrate();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Deliberate isolated drift fixture.
		$wpdb->query( "ALTER TABLE {$this->events_table} MODIFY metadata_json text DEFAULT NULL" );
		$this->set_schema_version( 7 );

		try {
			$this->rt108_runner( $wpdb )->migrate();
			self::fail( 'Expected incompatible metadata storage to block Migration 0008.' );
		} catch ( MigrationException ) {
			self::assertSame( 7, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
			self::assertSame( 'text', $this->column_type( $wpdb, 'metadata_json' ) );
		}
	}

	/**
	 * Version eight must fail closed when its Access Tokens predecessor is absent.
	 */
	public function test_missing_access_tokens_predecessor_blocks_events_creation(): void {
		global $wpdb;

		$this->rt107_runner( $wpdb )->migrate();
		$this->drop_table( $wpdb, $this->access_tokens_table );

		try {
			$this->rt108_runner( $wpdb )->migrate();
			self::fail( 'Expected missing Access Tokens schema to block Migration 0008.' );
		} catch ( MigrationException ) {
			self::assertSame( 7, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
			self::assertFalse( $this->table_exists( $wpdb, $this->events_table ) );
		}
	}

	/**
	 * Correlation IDs may group multiple events and optional fields remain nullable.
	 */
	public function test_duplicate_correlation_id_and_nullable_fields_are_supported(): void {
		global $wpdb;

		$this->rt108_runner( $wpdb )->migrate();
		$correlation_id = 'activation-flow-01HZX7M4';

		self::assertSame( 1, $this->insert_event( $wpdb, 'tag_activation_started', null, null, $correlation_id ) );
		self::assertSame( 1, $this->insert_event( $wpdb, 'tag_activated', 42, '{"attempt":1}', $correlation_id ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier with no external input.
		$rows = $wpdb->get_results( "SELECT actor_id, correlation_id, metadata_json FROM {$this->events_table} ORDER BY event_id ASC", ARRAY_A );
		self::assertCount( 2, $rows );
		self::assertNull( $rows[0]['actor_id'] );
		self::assertNull( $rows[0]['metadata_json'] );
		self::assertSame( $correlation_id, $rows[0]['correlation_id'] );
		self::assertSame( '42', $rows[1]['actor_id'] );
		self::assertSame( '{"attempt":1}', $rows[1]['metadata_json'] );
		self::assertSame( $correlation_id, $rows[1]['correlation_id'] );
	}

	/**
	 * Raw metadata must independently match the exact RT-108 contract.
	 */
	public function test_physical_schema_matches_independent_contract(): void {
		global $wpdb;

		$this->rt108_runner( $wpdb )->migrate();
		$this->assert_table_contract( $wpdb );
	}

	/**
	 * Migration SQL must honor a non-default WordPress prefix.
	 */
	public function test_migration_supports_non_default_table_prefix(): void {
		$database = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$result   = $database->set_prefix( 'rt108_' );
		$names    = new TableNames( $database->prefix );

		self::assertNotWPError( $result );
		self::assertSame( 'rt108_returntag_events', $names->events() );
		$this->clear_schema( $database, $names );

		try {
			$registry = $this->rt108_registry( $database );
			$runner   = new MigrationRunner(
				$registry,
				new WordPressSchemaVersionStore(),
				new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
			);

			self::assertSame( 8, $runner->migrate()->ending_version );
			self::assertTrue( $registry->all()[7]->verify() );
		} finally {
			$this->clear_schema( $database, $names );
		}
	}

	/**
	 * Build the isolated RT-108 registry.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function rt108_registry( wpdb $database ): MigrationRegistry {
		$names         = new TableNames( $database->prefix );
		$inspector     = new WordPressSchemaInspector( $database );
		$batches       = new CreateBatchesTableMigration( $database, $names, $inspector );
		$tags          = new CreateTagsTableMigration( $database, $names, $inspector, $batches );
		$exports       = new CreateBatchExportsTableMigration( $database, $names, $inspector, $tags );
		$challenges    = new CreateAuthChallengesTableMigration( $database, $names, $inspector, $exports );
		$conversations = new CreateConversationsTableMigration( $database, $names, $inspector, $challenges );
		$messages      = new CreateMessagesTableMigration( $database, $names, $inspector, $conversations );
		$access_tokens = new CreateAccessTokensTableMigration( $database, $names, $inspector, $messages );
		$events        = new CreateEventsTableMigration( $database, $names, $inspector, $access_tokens );

		return new MigrationRegistry( array( $batches, $tags, $exports, $challenges, $conversations, $messages, $access_tokens, $events ) );
	}

	/**
	 * Build a runner over the complete isolated RT-108 registry.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function rt108_runner( wpdb $database ): MigrationRunner {
		return $this->runner( $database, $this->rt108_registry( $database ) );
	}

	/**
	 * Build a runner over the first seven schema versions.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function rt107_runner( wpdb $database ): MigrationRunner {
		$migrations = array_slice( $this->rt108_registry( $database )->all(), 0, 7 );

		return $this->runner( $database, new MigrationRegistry( $migrations ) );
	}

	/**
	 * Build a Migration Runner for one isolated registry.
	 *
	 * @param wpdb              $database WordPress database adapter.
	 * @param MigrationRegistry $registry Ordered Migration registry.
	 */
	private function runner( wpdb $database, MigrationRegistry $registry ): MigrationRunner {
		return new MigrationRunner(
			$registry,
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
		);
	}

	/**
	 * Return version 0008 from the isolated composition.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function events_migration( wpdb $database ): CreateEventsTableMigration {
		$migration = $this->rt108_registry( $database )->all()[7];
		self::assertInstanceOf( CreateEventsTableMigration::class, $migration );

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
	 * Insert one hash-only access-token fixture.
	 *
	 * @param wpdb   $database   WordPress database adapter.
	 * @param string $token_hash Digest-shaped fixture; never a plaintext token.
	 * @return int|false Number of inserted rows or false.
	 */
	private function insert_access_token( wpdb $database, string $token_hash ): int|false {
		return $database->insert(
			$this->access_tokens_table,
			array(
				'conversation_id' => 101,
				'purpose'         => 'conversation_reply',
				'actor_role'      => 'owner',
				'token_hash'      => $token_hash,
				'expires_at'      => '2026-07-24 00:00:00',
				'created_at'      => '2026-07-23 00:00:00',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Insert one privacy-safe audit event fixture.
	 *
	 * @param wpdb        $database       WordPress database adapter.
	 * @param string      $event_type     Event type code.
	 * @param int|null    $actor_id       Optional WordPress actor ID.
	 * @param string|null $metadata_json  Optional safe JSON fixture.
	 * @param string      $correlation_id Shared operation correlation identifier.
	 * @return int|false Number of inserted rows or false.
	 */
	private function insert_event(
		wpdb $database,
		string $event_type,
		?int $actor_id,
		?string $metadata_json,
		string $correlation_id
	): int|false {
		return $database->insert(
			$this->events_table,
			array(
				'event_type'     => $event_type,
				'actor_type'     => null === $actor_id ? 'system' : 'user',
				'actor_id'       => $actor_id,
				'target_type'    => 'tag',
				'target_id'      => 'N7R2W9',
				'event_result'   => 'success',
				'correlation_id' => $correlation_id,
				'metadata_json'  => $metadata_json,
				'created_at'     => '2026-07-23 00:00:00',
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Assert exact columns, indexes, and absence of sensitive payload fields.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function assert_table_contract( wpdb $database ): void {
		$database_name = $database->get_var( 'SELECT DATABASE()' );
		self::assertIsString( $database_name );

		$table_query = $database->prepare(
			'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
			$database_name,
			$this->events_table
		);
		$table       = $database->get_row( $table_query, ARRAY_A );
		self::assertIsArray( $table );
		self::assertSame( 'InnoDB', $table['ENGINE'] );
		self::assertSame( $database->collate, $table['TABLE_COLLATION'] );

		$column_query = $database->prepare(
			'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_MAXIMUM_LENGTH, CHARACTER_SET_NAME, COLLATION_NAME, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s ORDER BY ORDINAL_POSITION',
			$database_name,
			$this->events_table
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
				'event_id'       => $this->metadata_column( 'bigint', true, false, null, null, null, null, true ),
				'event_type'     => $this->metadata_column( 'varchar', false, false, null, 64, 'ascii', 'ascii_bin' ),
				'actor_type'     => $this->metadata_column( 'varchar', false, false, null, 32, 'ascii', 'ascii_bin' ),
				'actor_id'       => $this->metadata_column( 'bigint', true, true ),
				'target_type'    => $this->metadata_column( 'varchar', false, false, null, 32, 'ascii', 'ascii_bin' ),
				'target_id'      => $this->metadata_column( 'varchar', false, false, null, 191, 'ascii', 'ascii_bin' ),
				'event_result'   => $this->metadata_column( 'varchar', false, false, null, 32, 'ascii', 'ascii_bin' ),
				'correlation_id' => $this->metadata_column( 'varchar', false, true, null, 64, 'ascii', 'ascii_bin' ),
				'metadata_json'  => $this->metadata_column( 'longtext', false, true, null, 4294967295, strtolower( (string) $database->charset ), strtolower( (string) $database->collate ) ),
				'created_at'     => $this->metadata_column( 'datetime' ),
			),
			$columns
		);

		$index_query = $database->prepare(
			'SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s ORDER BY INDEX_NAME, SEQ_IN_INDEX',
			$database_name,
			$this->events_table
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
				'PRIMARY'                          => array(
					'unique'  => true,
					'columns' => array( 'event_id' ),
				),
				'actor_type_actor_id_created_at'   => array(
					'unique'  => false,
					'columns' => array( 'actor_type', 'actor_id', 'created_at' ),
				),
				'correlation_id'                   => array(
					'unique'  => false,
					'columns' => array( 'correlation_id' ),
				),
				'created_at_event_id'              => array(
					'unique'  => false,
					'columns' => array( 'created_at', 'event_id' ),
				),
				'event_type_created_at'            => array(
					'unique'  => false,
					'columns' => array( 'event_type', 'created_at' ),
				),
				'target_type_target_id_created_at' => array(
					'unique'  => false,
					'columns' => array( 'target_type', 'target_id', 'created_at' ),
				),
			),
			$indexes
		);

		$foreign_key_query = $database->prepare(
			'SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = %s AND TABLE_NAME = %s',
			$database_name,
			$this->events_table
		);
		self::assertSame( array(), $database->get_col( $foreign_key_query ) );
		self::assertSame(
			array(),
			array_intersect(
				array( 'otp', 'token', 'email', 'message_body', 'order_id', 'claim_id', 'device_id', 'latitude', 'longitude', 'location' ),
				array_keys( $columns )
			)
		);
	}

	/**
	 * Build one normalized metadata expectation.
	 *
	 * @param string          $data_type      SQL data type.
	 * @param bool            $unsigned       Whether the integer is unsigned.
	 * @param bool            $nullable       Whether NULL is accepted.
	 * @param int|string|null $default_value  Exact database default.
	 * @param int|null        $maximum_length Character length.
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
			'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
			$database_name,
			$this->events_table,
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
	 * Remove only trusted RT-108 tables from the isolated database.
	 *
	 * @param wpdb       $database WordPress database adapter.
	 * @param TableNames $names    Trusted table-name mapping.
	 */
	private function clear_schema( wpdb $database, TableNames $names ): void {
		foreach ( array( $names->events(), $names->access_tokens(), $names->messages(), $names->conversations(), $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table_name ) {
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
