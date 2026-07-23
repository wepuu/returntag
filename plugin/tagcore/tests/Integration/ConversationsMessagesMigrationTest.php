<?php
/**
 * WordPress integration tests for RT-106 schema versions 0005 and 0006.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

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
 * Verifies fresh install, partial upgrade, retry, and privacy-safe storage.
 */
final class ConversationsMessagesMigrationTest extends WP_UnitTestCase {
	/**
	 * Physical Auth Challenges table in the isolated test database.
	 *
	 * @var string
	 */
	private string $challenges_table;

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
	 * Reset the isolated RT-106 schema before each test.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();

		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$names                     = new TableNames( $wpdb->prefix );
		$this->challenges_table    = $names->auth_challenges();
		$this->conversations_table = $names->conversations();
		$this->messages_table      = $names->messages();

		$this->clear_schema( $wpdb, $names );
	}

	/**
	 * Remove only isolated RT-106 fixtures after each test.
	 */
	protected function tearDown(): void {
		global $wpdb;

		$this->clear_schema( $wpdb, new TableNames( $wpdb->prefix ) );

		parent::tearDown();
	}

	/**
	 * The isolated registry must contain contiguous versions one through six.
	 */
	public function test_rt106_registry_registers_versions_one_through_six(): void {
		global $wpdb;

		$registry   = $this->rt106_registry( $wpdb );
		$migrations = $registry->all();

		self::assertSame( 6, $registry->target_version() );
		self::assertCount( 6, $migrations );
		self::assertInstanceOf( CreateConversationsTableMigration::class, $migrations[4] );
		self::assertInstanceOf( CreateMessagesTableMigration::class, $migrations[5] );
		self::assertSame( array( 1, 2, 3, 4, 5, 6 ), array_map( static fn( $migration ): int => $migration->version(), $migrations ) );
	}

	/**
	 * Fresh installation must apply and verify all six schema versions.
	 */
	public function test_fresh_install_advances_schema_from_zero_to_six(): void {
		global $wpdb;

		$report = $this->rt106_runner( $wpdb )->migrate();

		self::assertSame( 0, $report->starting_version );
		self::assertSame( 6, $report->ending_version );
		self::assertSame( array( 1, 2, 3, 4, 5, 6 ), $report->applied_versions );
		self::assertSame( 6, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
		self::assertTrue( $this->messages_migration( $wpdb )->verify() );
	}

	/**
	 * Upgrade from four must preserve predecessor data and definitions.
	 */
	public function test_upgrade_from_four_to_six_preserves_challenge_data(): void {
		global $wpdb;

		$this->rt105_runner( $wpdb )->migrate();
		$ciphertext = "\x00rt106-predecessor\xff";
		self::assertSame( 1, $this->insert_challenge( $wpdb, $ciphertext ) );
		$before = $this->show_create_table( $wpdb, $this->challenges_table );

		$report = $this->rt106_runner( $wpdb )->migrate();

		self::assertSame( 4, $report->starting_version );
		self::assertSame( 6, $report->ending_version );
		self::assertSame( array( 5, 6 ), $report->applied_versions );
		self::assertSame( $before, $this->show_create_table( $wpdb, $this->challenges_table ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier with no external input.
		self::assertSame( $ciphertext, $wpdb->get_var( "SELECT email_ciphertext FROM {$this->challenges_table} LIMIT 1" ) );
	}

	/**
	 * Complete schema and direct Migration execution must remain idempotent.
	 */
	public function test_complete_schema_is_idempotent(): void {
		global $wpdb;

		$runner = $this->rt106_runner( $wpdb );
		$runner->migrate();
		$conversations_before = $this->show_create_table( $wpdb, $this->conversations_table );
		$messages_before      = $this->show_create_table( $wpdb, $this->messages_table );

		$this->conversations_migration( $wpdb )->up();
		$this->messages_migration( $wpdb )->up();

		self::assertSame( $conversations_before, $this->show_create_table( $wpdb, $this->conversations_table ) );
		self::assertSame( $messages_before, $this->show_create_table( $wpdb, $this->messages_table ) );
		$report = $runner->migrate();
		self::assertSame( 6, $report->starting_version );
		self::assertSame( 6, $report->ending_version );
		self::assertSame( array(), $report->applied_versions );
	}

	/**
	 * Each target table can repair a missing expected index during retry.
	 */
	public function test_retry_repairs_missing_indexes_in_both_tables(): void {
		global $wpdb;

		$this->rt106_runner( $wpdb )->migrate();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Deliberate isolated drift fixture.
		$wpdb->query( "ALTER TABLE {$this->conversations_table} DROP INDEX status_expires_at" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Deliberate isolated drift fixture.
		$wpdb->query( "ALTER TABLE {$this->messages_table} DROP INDEX provider_message_id" );
		$this->set_schema_version( 4 );

		$report = $this->rt106_runner( $wpdb )->migrate();

		self::assertSame( array( 5, 6 ), $report->applied_versions );
		self::assertTrue( $this->messages_migration( $wpdb )->verify() );
	}

	/**
	 * A failed message Migration must retain the verified version-five state.
	 */
	public function test_message_failure_retains_version_five_and_retry_resumes_at_six(): void {
		global $wpdb;

		$this->rt105_runner( $wpdb )->migrate();
		$charset_collate = $wpdb->get_charset_collate();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Deliberate incompatible isolated table fixture.
		$wpdb->query( "CREATE TABLE {$this->messages_table} (message_id bigint(20) unsigned NOT NULL AUTO_INCREMENT, body_ciphertext longtext NOT NULL, PRIMARY KEY  (message_id)) ENGINE=InnoDB {$charset_collate}" );

		try {
			$this->rt106_runner( $wpdb )->migrate();
			self::fail( 'Expected incompatible message schema to block Migration 0006.' );
		} catch ( MigrationException ) {
			self::assertSame( 5, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
			self::assertTrue( $this->conversations_migration( $wpdb )->verify() );
			self::assertSame( 'longtext', $this->column_type( $wpdb, $this->messages_table, 'body_ciphertext' ) );
		}

		$this->drop_table( $wpdb, $this->messages_table );
		$report = $this->rt106_runner( $wpdb )->migrate();
		self::assertSame( 5, $report->starting_version );
		self::assertSame( array( 6 ), $report->applied_versions );
		self::assertTrue( $this->messages_migration( $wpdb )->verify() );
	}

	/**
	 * Version six must fail closed when its conversations predecessor is absent.
	 */
	public function test_missing_conversations_predecessor_blocks_messages_creation(): void {
		global $wpdb;

		$this->rt105_runner( $wpdb )->migrate();
		$this->conversations_migration( $wpdb )->up();
		$this->set_schema_version( 5 );
		$this->drop_table( $wpdb, $this->conversations_table );

		try {
			$this->rt106_runner( $wpdb )->migrate();
			self::fail( 'Expected missing conversations schema to block Migration 0006.' );
		} catch ( MigrationException ) {
			self::assertSame( 5, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
			self::assertFalse( $this->table_exists( $wpdb, $this->messages_table ) );
		}
	}

	/**
	 * Opaque ciphertext and safe defaults must round trip without false uniqueness.
	 */
	public function test_privacy_safe_ciphertext_and_delivery_defaults_round_trip(): void {
		global $wpdb;

		$this->rt106_runner( $wpdb )->migrate();
		$email_ciphertext = "\x00finder-envelope\xff";
		$lookup           = str_repeat( 'a', 64 );
		$conversation_id  = $this->insert_conversation( $wpdb, $email_ciphertext, $lookup );
		self::assertGreaterThan( 0, $conversation_id );
		self::assertGreaterThan( $conversation_id, $this->insert_conversation( $wpdb, "\x01second-finder-envelope", $lookup ) );

		$body = "\x00message-envelope\xfe";
		self::assertSame( 1, $this->insert_message( $wpdb, $conversation_id, $body ) );
		self::assertSame( 1, $this->insert_message( $wpdb, $conversation_id, "\x01second-envelope" ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier with no external input.
		$conversation = $wpdb->get_row( "SELECT * FROM {$this->conversations_table} ORDER BY conversation_id ASC LIMIT 1", ARRAY_A );
		self::assertIsArray( $conversation );
		self::assertSame( $email_ciphertext, $conversation['finder_email_ciphertext'] );
		self::assertNull( $conversation['finder_verified_at'] );
		self::assertSame( 'pending_verification', $conversation['conversation_status'] );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier with no external input.
		$message = $wpdb->get_row( "SELECT * FROM {$this->messages_table} ORDER BY message_id ASC LIMIT 1", ARRAY_A );
		self::assertIsArray( $message );
		self::assertSame( $body, $message['body_ciphertext'] );
		self::assertSame( 'queued', $message['delivery_status'] );
		self::assertNull( $message['provider_message_id'] );
		self::assertNull( $message['delivered_at'] );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier with a prepared value placeholder.
		$query = $wpdb->prepare( "SELECT COUNT(*) FROM {$this->conversations_table} WHERE finder_email_lookup = %s", strtoupper( $lookup ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query was prepared immediately above.
		self::assertSame( 0, (int) $wpdb->get_var( $query ) );
	}

	/**
	 * Raw metadata must independently match both exact RT-106 contracts.
	 */
	public function test_physical_schema_matches_independent_contracts(): void {
		global $wpdb;

		$this->rt106_runner( $wpdb )->migrate();
		$this->assert_conversations_contract( $wpdb );
		$this->assert_messages_contract( $wpdb );
	}

	/**
	 * Migration SQL must honor a non-default WordPress prefix.
	 */
	public function test_migrations_support_non_default_table_prefix(): void {
		$database = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$result   = $database->set_prefix( 'rt106_' );
		$names    = new TableNames( $database->prefix );

		self::assertNotWPError( $result );
		self::assertSame( 'rt106_returntag_conversations', $names->conversations() );
		self::assertSame( 'rt106_returntag_messages', $names->messages() );
		$this->clear_schema( $database, $names );

		try {
			$registry = $this->rt106_registry( $database );
			$runner   = new MigrationRunner(
				$registry,
				new WordPressSchemaVersionStore(),
				new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
			);

			self::assertSame( 6, $runner->migrate()->ending_version );
			self::assertTrue( $registry->all()[5]->verify() );
		} finally {
			$this->clear_schema( $database, $names );
		}
	}

	/**
	 * Build the isolated RT-106 registry.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function rt106_registry( wpdb $database ): MigrationRegistry {
		$names         = new TableNames( $database->prefix );
		$inspector     = new WordPressSchemaInspector( $database );
		$batches       = new CreateBatchesTableMigration( $database, $names, $inspector );
		$tags          = new CreateTagsTableMigration( $database, $names, $inspector, $batches );
		$exports       = new CreateBatchExportsTableMigration( $database, $names, $inspector, $tags );
		$challenges    = new CreateAuthChallengesTableMigration( $database, $names, $inspector, $exports );
		$conversations = new CreateConversationsTableMigration( $database, $names, $inspector, $challenges );
		$messages      = new CreateMessagesTableMigration( $database, $names, $inspector, $conversations );

		return new MigrationRegistry( array( $batches, $tags, $exports, $challenges, $conversations, $messages ) );
	}

	/**
	 * Build a runner over the complete isolated RT-106 registry.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function rt106_runner( wpdb $database ): MigrationRunner {
		return $this->runner( $database, $this->rt106_registry( $database ) );
	}

	/**
	 * Build a runner over the first four schema versions.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function rt105_runner( wpdb $database ): MigrationRunner {
		$migrations = array_slice( $this->rt106_registry( $database )->all(), 0, 4 );

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
	 * Return version 0005 from the isolated composition.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function conversations_migration( wpdb $database ): CreateConversationsTableMigration {
		$migration = $this->rt106_registry( $database )->all()[4];
		self::assertInstanceOf( CreateConversationsTableMigration::class, $migration );

		return $migration;
	}

	/**
	 * Return version 0006 from the isolated composition.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function messages_migration( wpdb $database ): CreateMessagesTableMigration {
		$migration = $this->rt106_registry( $database )->all()[5];
		self::assertInstanceOf( CreateMessagesTableMigration::class, $migration );

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
	 * Insert a privacy-safe authentication challenge fixture.
	 *
	 * @param wpdb   $database   WordPress database adapter.
	 * @param string $ciphertext Opaque ciphertext fixture.
	 * @return int|false Number of inserted rows or false.
	 */
	private function insert_challenge( wpdb $database, string $ciphertext ): int|false {
		return $database->insert(
			$this->challenges_table,
			array(
				'purpose'          => 'finder_verification',
				'subject_type'     => 'conversation',
				'subject_id'       => 'fixture-subject',
				'email_ciphertext' => $ciphertext,
				'email_lookup'     => str_repeat( 'b', 64 ),
				'code_hash'        => '$fixture$' . str_repeat( 'c', 56 ),
				'expires_at'       => '2026-07-23 00:10:00',
				'created_at'       => '2026-07-23 00:00:00',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Insert one privacy-safe conversation fixture.
	 *
	 * @param wpdb   $database   WordPress database adapter.
	 * @param string $ciphertext Opaque finder address envelope.
	 * @param string $lookup     HMAC-shaped lookup fixture.
	 */
	private function insert_conversation( wpdb $database, string $ciphertext, string $lookup ): int {
		$result = $database->insert(
			$this->conversations_table,
			array(
				'tag_id'                  => 'N7R2W9',
				'owner_id_snapshot'       => 42,
				'finder_email_ciphertext' => $ciphertext,
				'finder_email_lookup'     => $lookup,
				'conversation_status'     => 'pending_verification',
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
	 * @param int    $conversation_id Existing conversation ID.
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
	 * Assert the exact conversations table contract.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function assert_conversations_contract( wpdb $database ): void {
		$this->assert_table_contract(
			$database,
			$this->conversations_table,
			array(
				'conversation_id'         => $this->metadata_column( 'bigint', true, false, null, null, null, null, true ),
				'tag_id'                  => $this->metadata_column( 'char', false, false, null, 6, 'ascii', 'ascii_bin' ),
				'owner_id_snapshot'       => $this->metadata_column( 'bigint', true ),
				'finder_email_ciphertext' => $this->metadata_column( 'longblob', false, false, null, 4294967295 ),
				'finder_email_lookup'     => $this->metadata_column( 'char', false, false, null, 64, 'ascii', 'ascii_bin' ),
				'finder_verified_at'      => $this->metadata_column( 'datetime', false, true ),
				'conversation_status'     => $this->metadata_column( 'varchar', false, false, null, 32, 'ascii', 'ascii_bin' ),
				'expires_at'              => $this->metadata_column( 'datetime' ),
				'last_activity_at'        => $this->metadata_column( 'datetime' ),
				'created_at'              => $this->metadata_column( 'datetime' ),
			),
			array(
				'PRIMARY'                  => array(
					'unique'  => true,
					'columns' => array( 'conversation_id' ),
				),
				'finder_lookup_created_at' => array(
					'unique'  => false,
					'columns' => array( 'finder_email_lookup', 'created_at' ),
				),
				'owner_status_activity'    => array(
					'unique'  => false,
					'columns' => array( 'owner_id_snapshot', 'conversation_status', 'last_activity_at' ),
				),
				'status_expires_at'        => array(
					'unique'  => false,
					'columns' => array( 'conversation_status', 'expires_at' ),
				),
				'tag_id_status_activity'   => array(
					'unique'  => false,
					'columns' => array( 'tag_id', 'conversation_status', 'last_activity_at' ),
				),
			),
			array( 'finder_email', 'owner_email', 'location', 'device_id', 'order_id', 'claim_id' )
		);
	}

	/**
	 * Assert the exact messages table contract.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function assert_messages_contract( wpdb $database ): void {
		$this->assert_table_contract(
			$database,
			$this->messages_table,
			array(
				'message_id'          => $this->metadata_column( 'bigint', true, false, null, null, null, null, true ),
				'conversation_id'     => $this->metadata_column( 'bigint', true ),
				'sender_role'         => $this->metadata_column( 'varchar', false, false, null, 32, 'ascii', 'ascii_bin' ),
				'body_ciphertext'     => $this->metadata_column( 'longblob', false, false, null, 4294967295 ),
				'delivery_status'     => $this->metadata_column( 'varchar', false, false, 'queued', 32, 'ascii', 'ascii_bin' ),
				'provider_message_id' => $this->metadata_column( 'varchar', false, true, null, 191, 'ascii', 'ascii_bin' ),
				'delivered_at'        => $this->metadata_column( 'datetime', false, true ),
				'created_at'          => $this->metadata_column( 'datetime' ),
			),
			array(
				'PRIMARY'                    => array(
					'unique'  => true,
					'columns' => array( 'message_id' ),
				),
				'conversation_message'       => array(
					'unique'  => false,
					'columns' => array( 'conversation_id', 'message_id' ),
				),
				'delivery_status_created_at' => array(
					'unique'  => false,
					'columns' => array( 'delivery_status', 'created_at' ),
				),
				'provider_message_id'        => array(
					'unique'  => false,
					'columns' => array( 'provider_message_id' ),
				),
			),
			array( 'body', 'email', 'reply_to', 'attachment', 'location', 'order_id', 'claim_id' )
		);
	}

	/**
	 * Assert exact columns, indexes, and absence of physical foreign keys.
	 *
	 * @param wpdb   $database          WordPress database adapter.
	 * @param string $table_name        Trusted table name.
	 * @param array  $expected_columns  Exact normalized column metadata.
	 * @param array  $expected_indexes  Exact normalized index metadata.
	 * @param array  $forbidden_columns Column names that must remain absent.
	 */
	private function assert_table_contract( wpdb $database, string $table_name, array $expected_columns, array $expected_indexes, array $forbidden_columns ): void {
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

		self::assertSame( $expected_columns, $columns );

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
		self::assertSame( $expected_indexes, $indexes );

		$foreign_key_query = $database->prepare(
			'SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = %s AND TABLE_NAME = %s',
			$database_name,
			$table_name
		);
		self::assertSame( array(), $database->get_col( $foreign_key_query ) );
		self::assertSame( array(), array_intersect( $forbidden_columns, array_keys( $columns ) ) );
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

		if ( 'NULL' === strtoupper( $normalized ) ) {
			return null;
		}

		if ( 2 <= strlen( $normalized ) && "'" === $normalized[0] && "'" === $normalized[ strlen( $normalized ) - 1 ] ) {
			$normalized = substr( $normalized, 1, -1 );
			$normalized = str_replace( "''", "'", $normalized );
		}

		return $normalized;
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
	 * Remove only trusted RT-106 tables from the isolated database.
	 *
	 * @param wpdb       $database WordPress database adapter.
	 * @param TableNames $names    Trusted table-name mapping.
	 */
	private function clear_schema( wpdb $database, TableNames $names ): void {
		foreach ( array( $names->messages(), $names->conversations(), $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table_name ) {
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
