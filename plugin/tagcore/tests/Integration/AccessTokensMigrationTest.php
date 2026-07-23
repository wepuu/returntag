<?php
/**
 * WordPress integration tests for RT-107 schema version 0007.
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
 * Verifies fresh install, upgrade, retry, uniqueness, and hash-only storage.
 */
final class AccessTokensMigrationTest extends WP_UnitTestCase {
	/**
	 * Physical Conversations table in the isolated test database.
	 *
	 * @var string
	 */
	private string $conversations_table;

	/**
	 * Physical Messages table in the isolated test database.
	 *
	 * @var string
	 */
	private string $messages_table;

	/**
	 * Physical Access Tokens table in the isolated test database.
	 *
	 * @var string
	 */
	private string $access_tokens_table;

	/**
	 * Reset the isolated RT-107 schema before each test.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();

		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$names                     = new TableNames( $wpdb->prefix );
		$this->conversations_table = $names->conversations();
		$this->messages_table      = $names->messages();
		$this->access_tokens_table = $names->access_tokens();

		$this->clear_schema( $wpdb, $names );
	}

	/**
	 * Remove only isolated RT-107 fixtures after each test.
	 */
	protected function tearDown(): void {
		global $wpdb;

		$this->clear_schema( $wpdb, new TableNames( $wpdb->prefix ) );

		parent::tearDown();
	}

	/**
	 * The isolated registry must contain contiguous versions one through seven.
	 */
	public function test_rt107_registry_registers_versions_one_through_seven(): void {
		global $wpdb;

		$registry   = $this->rt107_registry( $wpdb );
		$migrations = $registry->all();

		self::assertSame( 7, $registry->target_version() );
		self::assertCount( 7, $migrations );
		self::assertInstanceOf( CreateAccessTokensTableMigration::class, $migrations[6] );
		self::assertSame( array( 1, 2, 3, 4, 5, 6, 7 ), array_map( static fn( $migration ): int => $migration->version(), $migrations ) );
	}

	/**
	 * Fresh installation must apply and verify all seven schema versions.
	 */
	public function test_fresh_install_advances_schema_from_zero_to_seven(): void {
		global $wpdb;

		$report = $this->rt107_runner( $wpdb )->migrate();

		self::assertSame( 0, $report->starting_version );
		self::assertSame( 7, $report->ending_version );
		self::assertSame( array( 1, 2, 3, 4, 5, 6, 7 ), $report->applied_versions );
		self::assertSame( 7, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
		self::assertTrue( $this->access_tokens_migration( $wpdb )->verify() );
	}

	/**
	 * Upgrade from six must preserve conversation and message data.
	 */
	public function test_upgrade_from_six_to_seven_preserves_message_data(): void {
		global $wpdb;

		$this->rt106_runner( $wpdb )->migrate();
		$conversation_id = $this->insert_conversation( $wpdb );
		self::assertGreaterThan( 0, $conversation_id );
		$message_body = "\x00rt107-message-envelope\xff";
		self::assertSame( 1, $this->insert_message( $wpdb, $conversation_id, $message_body ) );
		$messages_before = $this->show_create_table( $wpdb, $this->messages_table );

		$report = $this->rt107_runner( $wpdb )->migrate();

		self::assertSame( 6, $report->starting_version );
		self::assertSame( 7, $report->ending_version );
		self::assertSame( array( 7 ), $report->applied_versions );
		self::assertSame( $messages_before, $this->show_create_table( $wpdb, $this->messages_table ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier with no external input.
		self::assertSame( $message_body, $wpdb->get_var( "SELECT body_ciphertext FROM {$this->messages_table} LIMIT 1" ) );
	}

	/**
	 * Complete schema and direct Migration execution must remain idempotent.
	 */
	public function test_complete_schema_is_idempotent(): void {
		global $wpdb;

		$runner = $this->rt107_runner( $wpdb );
		$runner->migrate();
		$before = $this->show_create_table( $wpdb, $this->access_tokens_table );

		$this->access_tokens_migration( $wpdb )->up();

		self::assertSame( $before, $this->show_create_table( $wpdb, $this->access_tokens_table ) );
		$report = $runner->migrate();
		self::assertSame( 7, $report->starting_version );
		self::assertSame( 7, $report->ending_version );
		self::assertSame( array(), $report->applied_versions );
	}

	/**
	 * A missing expected index must be restored during a safe retry.
	 */
	public function test_retry_repairs_missing_expiry_index(): void {
		global $wpdb;

		$this->rt107_runner( $wpdb )->migrate();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Deliberate isolated drift fixture.
		$wpdb->query( "ALTER TABLE {$this->access_tokens_table} DROP INDEX expires_revoked_at" );
		$this->set_schema_version( 6 );

		$report = $this->rt107_runner( $wpdb )->migrate();

		self::assertSame( array( 7 ), $report->applied_versions );
		self::assertTrue( $this->access_tokens_migration( $wpdb )->verify() );
	}

	/**
	 * Incompatible token-hash storage must fail before dbDelta can convert it.
	 */
	public function test_incompatible_token_hash_is_preserved_and_version_stays_six(): void {
		global $wpdb;

		$this->rt107_runner( $wpdb )->migrate();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Deliberate isolated drift fixture.
		$wpdb->query( "ALTER TABLE {$this->access_tokens_table} MODIFY token_hash char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL" );
		$this->set_schema_version( 6 );

		try {
			$this->rt107_runner( $wpdb )->migrate();
			self::fail( 'Expected incompatible token hash schema to block Migration 0007.' );
		} catch ( MigrationException ) {
			self::assertSame( 6, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
			self::assertSame( 'char(32)', $this->column_type( $wpdb, 'token_hash' ) );
		}
	}

	/**
	 * Version seven must fail closed when its Messages predecessor is absent.
	 */
	public function test_missing_messages_predecessor_blocks_access_tokens_creation(): void {
		global $wpdb;

		$this->rt106_runner( $wpdb )->migrate();
		$this->drop_table( $wpdb, $this->messages_table );

		try {
			$this->rt107_runner( $wpdb )->migrate();
			self::fail( 'Expected missing Messages schema to block Migration 0007.' );
		} catch ( MigrationException ) {
			self::assertSame( 6, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
			self::assertFalse( $this->table_exists( $wpdb, $this->access_tokens_table ) );
		}
	}

	/**
	 * Hash uniqueness and nullable lifecycle defaults must be database enforced.
	 */
	public function test_hash_uniqueness_and_lifecycle_defaults(): void {
		global $wpdb;

		$this->rt107_runner( $wpdb )->migrate();
		$hash = str_repeat( 'a', 64 );

		self::assertSame( 1, $this->insert_access_token( $wpdb, $hash ) );
		self::assertSame( 1, $this->insert_access_token( $wpdb, str_repeat( 'b', 64 ) ) );

		$previous_suppression = $wpdb->suppress_errors( true );
		try {
			self::assertFalse( $this->insert_access_token( $wpdb, $hash ) );
		} finally {
			$wpdb->suppress_errors( $previous_suppression );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier with no external input.
		$row = $wpdb->get_row( "SELECT * FROM {$this->access_tokens_table} ORDER BY token_id ASC LIMIT 1", ARRAY_A );
		self::assertIsArray( $row );
		self::assertSame( $hash, $row['token_hash'] );
		self::assertNull( $row['exchanged_at'] );
		self::assertNull( $row['revoked_at'] );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier with no external input.
		self::assertSame( 2, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->access_tokens_table}" ) );
	}

	/**
	 * Raw metadata must independently match the exact RT-107 contract.
	 */
	public function test_physical_schema_matches_independent_contract(): void {
		global $wpdb;

		$this->rt107_runner( $wpdb )->migrate();
		$this->assert_table_contract( $wpdb );
	}

	/**
	 * Migration SQL must honor a non-default WordPress prefix.
	 */
	public function test_migration_supports_non_default_table_prefix(): void {
		$database = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$result   = $database->set_prefix( 'rt107_' );
		$names    = new TableNames( $database->prefix );

		self::assertNotWPError( $result );
		self::assertSame( 'rt107_returntag_access_tokens', $names->access_tokens() );
		$this->clear_schema( $database, $names );

		try {
			$registry = $this->rt107_registry( $database );
			$runner   = new MigrationRunner(
				$registry,
				new WordPressSchemaVersionStore(),
				new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
			);

			self::assertSame( 7, $runner->migrate()->ending_version );
			self::assertTrue( $registry->all()[6]->verify() );
		} finally {
			$this->clear_schema( $database, $names );
		}
	}

	/**
	 * Build the isolated RT-107 registry.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function rt107_registry( wpdb $database ): MigrationRegistry {
		$names         = new TableNames( $database->prefix );
		$inspector     = new WordPressSchemaInspector( $database );
		$batches       = new CreateBatchesTableMigration( $database, $names, $inspector );
		$tags          = new CreateTagsTableMigration( $database, $names, $inspector, $batches );
		$exports       = new CreateBatchExportsTableMigration( $database, $names, $inspector, $tags );
		$challenges    = new CreateAuthChallengesTableMigration( $database, $names, $inspector, $exports );
		$conversations = new CreateConversationsTableMigration( $database, $names, $inspector, $challenges );
		$messages      = new CreateMessagesTableMigration( $database, $names, $inspector, $conversations );
		$access_tokens = new CreateAccessTokensTableMigration( $database, $names, $inspector, $messages );

		return new MigrationRegistry( array( $batches, $tags, $exports, $challenges, $conversations, $messages, $access_tokens ) );
	}

	/**
	 * Build a runner over the complete isolated RT-107 registry.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function rt107_runner( wpdb $database ): MigrationRunner {
		return $this->runner( $database, $this->rt107_registry( $database ) );
	}

	/**
	 * Build a runner over the first six schema versions.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function rt106_runner( wpdb $database ): MigrationRunner {
		$migrations = array_slice( $this->rt107_registry( $database )->all(), 0, 6 );

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
	 * Return version 0007 from the isolated composition.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function access_tokens_migration( wpdb $database ): CreateAccessTokensTableMigration {
		$migration = $this->rt107_registry( $database )->all()[6];
		self::assertInstanceOf( CreateAccessTokensTableMigration::class, $migration );

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
	 * Insert one privacy-safe conversation fixture.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function insert_conversation( wpdb $database ): int {
		$result = $database->insert(
			$this->conversations_table,
			array(
				'tag_id'                  => 'N7R2W9',
				'owner_id_snapshot'       => 42,
				'finder_email_ciphertext' => "\x00finder-envelope\xff",
				'finder_email_lookup'     => str_repeat( 'c', 64 ),
				'conversation_status'     => 'open',
				'expires_at'              => '2026-07-30 00:00:00',
				'last_activity_at'        => '2026-07-23 00:00:00',
				'created_at'              => '2026-07-23 00:00:00',
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $result ? 0 : (int) $database->insert_id;
	}

	/**
	 * Insert one encrypted message fixture.
	 *
	 * @param wpdb   $database        WordPress database adapter.
	 * @param int    $conversation_id Existing Conversation ID.
	 * @param string $ciphertext      Opaque message envelope.
	 * @return int|false Number of inserted rows or false.
	 */
	private function insert_message( wpdb $database, int $conversation_id, string $ciphertext ): int|false {
		return $database->insert(
			$this->messages_table,
			array(
				'conversation_id' => $conversation_id,
				'sender_role'     => 'finder',
				'body_ciphertext' => $ciphertext,
				'created_at'      => '2026-07-23 00:00:00',
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Insert one hash-only access token fixture.
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
				'actor_role'      => 'finder',
				'token_hash'      => $token_hash,
				'expires_at'      => '2026-07-24 00:00:00',
				'created_at'      => '2026-07-23 00:00:00',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Assert exact columns, indexes, and absence of plaintext token storage.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function assert_table_contract( wpdb $database ): void {
		$database_name = $database->get_var( 'SELECT DATABASE()' );
		self::assertIsString( $database_name );

		$table_query = $database->prepare(
			'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
			$database_name,
			$this->access_tokens_table
		);
		$table       = $database->get_row( $table_query, ARRAY_A );
		self::assertIsArray( $table );
		self::assertSame( 'InnoDB', $table['ENGINE'] );
		self::assertSame( $database->collate, $table['TABLE_COLLATION'] );

		$column_query = $database->prepare(
			'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_MAXIMUM_LENGTH, CHARACTER_SET_NAME, COLLATION_NAME, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s ORDER BY ORDINAL_POSITION',
			$database_name,
			$this->access_tokens_table
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
				'token_id'        => $this->metadata_column( 'bigint', true, false, null, null, null, null, true ),
				'conversation_id' => $this->metadata_column( 'bigint', true ),
				'purpose'         => $this->metadata_column( 'varchar', false, false, null, 32, 'ascii', 'ascii_bin' ),
				'actor_role'      => $this->metadata_column( 'varchar', false, false, null, 32, 'ascii', 'ascii_bin' ),
				'token_hash'      => $this->metadata_column( 'char', false, false, null, 64, 'ascii', 'ascii_bin' ),
				'expires_at'      => $this->metadata_column( 'datetime' ),
				'exchanged_at'    => $this->metadata_column( 'datetime', false, true ),
				'revoked_at'      => $this->metadata_column( 'datetime', false, true ),
				'created_at'      => $this->metadata_column( 'datetime' ),
			),
			$columns
		);

		$index_query = $database->prepare(
			'SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s ORDER BY INDEX_NAME, SEQ_IN_INDEX',
			$database_name,
			$this->access_tokens_table
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
				'PRIMARY'                    => array(
					'unique'  => true,
					'columns' => array( 'token_id' ),
				),
				'conversation_purpose_actor' => array(
					'unique'  => false,
					'columns' => array( 'conversation_id', 'purpose', 'actor_role' ),
				),
				'expires_revoked_at'         => array(
					'unique'  => false,
					'columns' => array( 'expires_at', 'revoked_at' ),
				),
				'token_hash_unique'          => array(
					'unique'  => true,
					'columns' => array( 'token_hash' ),
				),
			),
			$indexes
		);

		$foreign_key_query = $database->prepare(
			'SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = %s AND TABLE_NAME = %s',
			$database_name,
			$this->access_tokens_table
		);
		self::assertSame( array(), $database->get_col( $foreign_key_query ) );
		self::assertSame(
			array(),
			array_intersect(
				array( 'token', 'plaintext_token', 'email', 'ip_address', 'user_agent', 'owner_id', 'order_id', 'claim_id', 'location' ),
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
			'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
			$database_name,
			$this->access_tokens_table,
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
	 * Remove only trusted RT-107 tables from the isolated database.
	 *
	 * @param wpdb       $database WordPress database adapter.
	 * @param TableNames $names    Trusted table-name mapping.
	 */
	private function clear_schema( wpdb $database, TableNames $names ): void {
		foreach ( array( $names->access_tokens(), $names->messages(), $names->conversations(), $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table_name ) {
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
