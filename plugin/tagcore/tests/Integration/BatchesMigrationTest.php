<?php
/**
 * WordPress integration tests for RT-102 schema version 0001.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Infrastructure\Migration\CreateBatchesTableMigration;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationException;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use WP_UnitTestCase;

/**
 * Verifies fresh install, retry, idempotency, and data constraints.
 */
final class BatchesMigrationTest extends WP_UnitTestCase {
	/**
	 * Physical batches table in the isolated WordPress test database.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Reset the RT-102 isolated schema fixture before each test.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();

		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->table_name = ( new TableNames( $wpdb->prefix ) )->batches();
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
		$this->drop_batches_table();
	}

	/**
	 * Remove only the isolated RT-102 table fixture after each test.
	 */
	protected function tearDown(): void {
		$this->drop_batches_table();
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );

		parent::tearDown();
	}

	/**
	 * Fresh installation must create and verify version one.
	 */
	public function test_fresh_install_advances_schema_from_zero_to_one(): void {
		global $wpdb;

		$registry = ( new MigrationRegistryFactory( $wpdb ) )->create();
		$report   = $this->runner()->migrate();

		self::assertSame( 1, $registry->target_version() );
		self::assertSame( 0, $report->starting_version );
		self::assertSame( 1, $report->ending_version );
		self::assertSame( array( 1 ), $report->applied_versions );
		self::assertSame( 1, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
		self::assertTrue( $this->migration()->verify() );
		self::assertSame( $wpdb->prefix . 'returntag_batches', $this->table_name );
	}

	/**
	 * Migration SQL and verification must honor a non-default WordPress prefix.
	 */
	public function test_migration_supports_non_default_table_prefix(): void {
		$alternate_database = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$prefix_result      = $alternate_database->set_prefix( 'rt102_' );
		$alternate_table    = ( new TableNames( $alternate_database->prefix ) )->batches();

		self::assertNotWPError( $prefix_result );
		self::assertSame( 'rt102_returntag_batches', $alternate_table );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated alternate-prefix integration fixture.
		$alternate_database->query( "DROP TABLE IF EXISTS {$alternate_table}" );

		try {
			$registry = ( new MigrationRegistryFactory( $alternate_database ) )->create();
			$runner   = new MigrationRunner(
				$registry,
				new WordPressSchemaVersionStore(),
				new WordPressAdvisoryMigrationLock( $alternate_database, get_current_blog_id(), 0 )
			);

			self::assertSame( 1, $runner->migrate()->ending_version );
			self::assertTrue( $registry->all()[0]->verify() );
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated alternate-prefix integration cleanup.
			$alternate_database->query( "DROP TABLE IF EXISTS {$alternate_table}" );
			delete_option( WordPressSchemaVersionStore::OPTION_NAME );
		}
	}

	/**
	 * DbDelta and the Runner must both remain idempotent on a complete table.
	 */
	public function test_complete_table_is_idempotent(): void {
		$runner = $this->runner();
		$runner->migrate();
		$before = $this->show_create_table();

		$this->migration()->up();

		self::assertSame( $before, $this->show_create_table() );
		self::assertTrue( $this->migration()->verify() );

		$second_report = $runner->migrate();

		self::assertSame( 1, $second_report->starting_version );
		self::assertSame( 1, $second_report->ending_version );
		self::assertSame( array(), $second_report->applied_versions );
	}

	/**
	 * A safely repairable missing index must be restored on retry.
	 */
	public function test_retry_repairs_missing_index(): void {
		global $wpdb;

		$this->runner()->migrate();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted test table in the isolated integration database.
		$wpdb->query( "ALTER TABLE {$this->table_name} DROP INDEX batch_status_created_at" );
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );

		self::assertFalse( $this->migration()->verify() );

		$report = $this->runner()->migrate();

		self::assertSame( array( 1 ), $report->applied_versions );
		self::assertSame( 1, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
		self::assertTrue( $this->migration()->verify() );
	}

	/**
	 * An unsafe engine mismatch must fail without recording version one.
	 */
	public function test_wrong_engine_fails_verification_without_advancing_version(): void {
		global $wpdb;

		$this->runner()->migrate();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Deliberate isolated failure fixture.
		$wpdb->query( "ALTER TABLE {$this->table_name} ENGINE=MyISAM" );
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );

		self::assertFalse( $this->migration()->verify() );

		try {
			$this->runner()->migrate();
			self::fail( 'Expected the unsafe engine mismatch to fail verification.' );
		} catch ( MigrationException ) {
			self::assertSame( 0, get_option( WordPressSchemaVersionStore::OPTION_NAME, 0 ) );
			self::assertFalse( $this->migration()->verify() );
		}
	}

	/**
	 * Column defaults and batch-code uniqueness must match the contract.
	 */
	public function test_defaults_and_case_sensitive_batch_code_uniqueness(): void {
		global $wpdb;

		$this->runner()->migrate();

		self::assertSame( 1, $this->insert_batch( 'RT-102-BATCH' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier with a prepared value placeholder.
		$query = $wpdb->prepare( "SELECT smart_network, generated_quantity, batch_status, activation_enabled FROM {$this->table_name} WHERE batch_code = %s", 'RT-102-BATCH' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query was prepared immediately above.
		$row = $wpdb->get_row( $query, ARRAY_A );

		self::assertIsArray( $row );
		self::assertSame( 'none', $row['smart_network'] );
		self::assertSame( '0', $row['generated_quantity'] );
		self::assertSame( 'draft', $row['batch_status'] );
		self::assertSame( '0', $row['activation_enabled'] );
		self::assertSame( 1, $this->insert_batch( 'rt-102-batch' ) );

		$previous_suppression = $wpdb->suppress_errors( true );

		try {
			self::assertFalse( $this->insert_batch( 'RT-102-BATCH' ) );
		} finally {
			$wpdb->suppress_errors( $previous_suppression );
		}
	}

	/**
	 * The physical table must not contain forbidden relationship or network data.
	 */
	public function test_schema_contains_no_forbidden_fields(): void {
		global $wpdb;

		$this->runner()->migrate();
		$database_name = $wpdb->get_var( 'SELECT DATABASE()' );
		self::assertIsString( $database_name );

		$columns          = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
				$database_name,
				$this->table_name
			)
		);
		$forbidden_fields = array(
			'claim_id',
			'order_id',
			'order_item_id',
			'shipment_id',
			'tracking_number',
			'apple_account_id',
			'google_account_id',
			'device_id',
			'pairing_state',
			'latitude',
			'longitude',
		);

		self::assertSame( array(), array_intersect( $forbidden_fields, $columns ) );
	}

	/**
	 * Build the production registry and Runner against the isolated test database.
	 */
	private function runner(): MigrationRunner {
		global $wpdb;

		return new MigrationRunner(
			( new MigrationRegistryFactory( $wpdb ) )->create(),
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $wpdb, get_current_blog_id(), 0 )
		);
	}

	/**
	 * Return version 0001 from the production Migration registry.
	 */
	private function migration(): CreateBatchesTableMigration {
		global $wpdb;

		$migration = ( new MigrationRegistryFactory( $wpdb ) )->create()->all()[0];
		self::assertInstanceOf( CreateBatchesTableMigration::class, $migration );

		return $migration;
	}

	/**
	 * Insert the minimum schema-level fixture and allow database defaults to run.
	 *
	 * @param string $batch_code Case-sensitive fixture batch code.
	 * @return int|false Number of inserted rows or false on constraint failure.
	 */
	private function insert_batch( string $batch_code ): int|false {
		global $wpdb;

		return $wpdb->insert(
			$this->table_name,
			array(
				'batch_code'         => $batch_code,
				'tag_type'           => 'sticker',
				'requested_quantity' => 100,
				'created_by'         => 1,
				'created_at'         => '2026-07-22 00:00:00',
				'updated_at'         => '2026-07-22 00:00:00',
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Capture the canonical table definition for idempotency comparison.
	 */
	private function show_create_table(): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted test table identifier.
		$row = $wpdb->get_row( "SHOW CREATE TABLE {$this->table_name}", ARRAY_N );
		self::assertIsArray( $row );
		self::assertIsString( $row[1] ?? null );

		return $row[1];
	}

	/**
	 * Drop only the trusted RT-102 table in the isolated integration database.
	 */
	private function drop_batches_table(): void {
		global $wpdb;

		if ( ! isset( $this->table_name ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated integration-test cleanup for a trusted table.
		$wpdb->query( "DROP TABLE IF EXISTS {$this->table_name}" );
	}
}
