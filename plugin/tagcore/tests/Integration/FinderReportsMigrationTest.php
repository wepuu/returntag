<?php
/**
 * RT-315 Finder Report schema integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Infrastructure\Migration\MigrationException;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistry;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use WP_UnitTestCase;
use wpdb;

/**
 * Verifies fresh install, Schema-8 upgrade, retry, cardinality, and privacy.
 */
final class FinderReportsMigrationTest extends WP_UnitTestCase {
	/**
	 * Trusted table-name mapping.
	 *
	 * @var TableNames
	 */
	private TableNames $tables;

	/** Build a clean isolated schema fixture. */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->tables = new TableNames( $wpdb->prefix );
		$this->clear_schema( $wpdb );
	}

	/** Remove the isolated schema fixture. */
	protected function tearDown(): void {
		global $wpdb;

		$this->clear_schema( $wpdb );
		parent::tearDown();
	}

	/** Fresh install must create both independent tables through Schema 11. */
	public function test_fresh_install_creates_schema_eleven(): void {
		global $wpdb;

		$report = $this->runner( $wpdb, 11 )->migrate();

		self::assertSame( 11, $report->ending_version );
		self::assertSame( range( 1, 11 ), $report->applied_versions );
		self::assertTrue( $this->table_exists( $wpdb, $this->tables->finder_reports() ) );
		self::assertTrue( $this->table_exists( $wpdb, $this->tables->finder_report_media() ) );
	}

	/** Schema-8 upgrade must preserve predecessor data and apply 9 through 11. */
	public function test_schema_eight_upgrade_preserves_predecessor_data(): void {
		global $wpdb;

		$this->runner( $wpdb, 8 )->migrate();
		$this->insert_batch( $wpdb, 'RT315-UPGRADE' );

		$report = $this->runner( $wpdb, 11 )->migrate();
		$count  = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE batch_code = %s',
				$this->tables->batches(),
				'RT315-UPGRADE'
			)
		);

		self::assertSame( array( 9, 10, 11 ), $report->applied_versions );
		self::assertSame( '1', (string) $count );
	}

	/** A complete schema retry must be idempotent and issue no DDL. */
	public function test_complete_schema_retry_is_idempotent(): void {
		global $wpdb;

		$this->runner( $wpdb, 11 )->migrate();
		$ddl      = array();
		$observer = static function ( string $query ) use ( &$ddl ): string {
			if ( 1 === preg_match( '/^\s*(?:ALTER|CREATE|DROP|RENAME|TRUNCATE)\b/i', $query ) ) {
				$ddl[] = $query;
			}

			return $query;
		};
		add_filter( 'query', $observer );

		try {
			$report = $this->runner( $wpdb, 11 )->migrate();
		} finally {
			remove_filter( 'query', $observer );
		}

		self::assertSame( array(), $report->applied_versions );
		self::assertSame( array(), $ddl );
	}

	/** Schema-10 upgrade adds only Migration 0011 and preserves report data. */
	public function test_schema_ten_upgrade_adds_conversation_link_and_preserves_reports(): void {
		global $wpdb;

		$this->runner( $wpdb, 10 )->migrate();
		$report_id = $this->insert_report_fixture( $wpdb );
		$report    = $this->runner( $wpdb, 11 )->migrate();
		$stored    = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT finder_report_id FROM %i WHERE finder_report_id = %d',
				$this->tables->finder_reports(),
				$report_id
			)
		);

		self::assertSame( array( 11 ), $report->applied_versions );
		self::assertSame( (string) $report_id, (string) $stored );
	}

	/** Schema 11 enforces one canonical Conversation link across reports. */
	public function test_conversation_link_is_unique_across_reports(): void {
		global $wpdb;

		$this->runner( $wpdb, 11 )->migrate();
		$first_report_id = $this->insert_report_fixture( $wpdb );
		self::assertSame(
			1,
			$wpdb->insert(
				$this->tables->conversations(),
				array(
					'tag_id'                  => 'N7R2W9',
					'owner_id_snapshot'       => 42,
					'finder_email_ciphertext' => 'opaque-email',
					'finder_email_lookup'     => str_repeat( 'a', 64 ),
					'finder_verified_at'      => '2026-08-04 00:00:00',
					'conversation_status'     => 'open',
					'expires_at'              => '2026-09-03 00:00:00',
					'last_activity_at'        => '2026-08-04 00:00:00',
					'created_at'              => '2026-08-04 00:00:00',
				)
			)
		);
		$conversation_id = (int) $wpdb->insert_id;
		self::assertSame( 1, $wpdb->update( $this->tables->finder_reports(), array( 'conversation_id' => $conversation_id ), array( 'finder_report_id' => $first_report_id ), array( '%d' ), array( '%d' ) ) );

		$second_report_id = $this->insert_duplicate_report( $wpdb, $first_report_id );
		$wpdb->suppress_errors( true );
		try {
			self::assertFalse( $wpdb->update( $this->tables->finder_reports(), array( 'conversation_id' => $conversation_id ), array( 'finder_report_id' => $second_report_id ), array( '%d' ), array( '%d' ) ) );
		} finally {
			$wpdb->suppress_errors( false );
		}
	}

	/** The media table must enforce exactly one row per Finder Report. */
	public function test_media_cardinality_is_unique_per_report(): void {
		global $wpdb;

		$this->runner( $wpdb, 11 )->migrate();
		$report_id = $this->insert_report_fixture( $wpdb );

		self::assertSame( 1, $this->insert_media_fixture( $wpdb, $report_id, str_repeat( 'a', 64 ) ) );
		$wpdb->suppress_errors( true );
		try {
			self::assertFalse( $this->insert_media_fixture( $wpdb, $report_id, str_repeat( 'b', 64 ) ) );
		} finally {
			$wpdb->suppress_errors( false );
		}
	}

	/** Tables must omit public URLs, filenames, email, and location fields. */
	public function test_schema_omits_forbidden_private_and_public_fields(): void {
		global $wpdb;

		$this->runner( $wpdb, 11 )->migrate();
		$columns = array_merge(
			$this->column_names( $wpdb, $this->tables->finder_reports() ),
			$this->column_names( $wpdb, $this->tables->finder_report_media() )
		);

		self::assertSame(
			array(),
			array_intersect(
				array( 'finder_email', 'owner_email', 'public_url', 'source_filename', 'original_filename', 'exif', 'gps', 'latitude', 'longitude', 'location', 'access_token' ),
				$columns
			)
		);
	}

	/** Version 10 must fail closed when the version-9 predecessor is absent. */
	public function test_missing_reports_predecessor_blocks_media_creation(): void {
		global $wpdb;

		$this->runner( $wpdb, 9 )->migrate();
		$this->drop_table( $wpdb, $this->tables->finder_reports() );

		try {
			$this->runner( $wpdb, 10 )->migrate();
			self::fail( 'Expected the missing Finder Reports predecessor to block Migration 0010.' );
		} catch ( MigrationException ) {
			self::assertSame( 9, get_option( WordPressSchemaVersionStore::OPTION_NAME ) );
			self::assertFalse( $this->table_exists( $wpdb, $this->tables->finder_report_media() ) );
		}
	}

	/**
	 * Build a production-prefix Migration runner.
	 *
	 * @param wpdb $database Database adapter.
	 * @param int  $target Last migration to include.
	 */
	private function runner( wpdb $database, int $target ): MigrationRunner {
		$all      = ( new MigrationRegistryFactory( $database ) )->create()->all();
		$registry = new MigrationRegistry( array_slice( $all, 0, $target ) );

		return new MigrationRunner(
			$registry,
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
		);
	}

	/**
	 * Insert one synthetic predecessor Batch.
	 *
	 * @param wpdb   $database Database adapter.
	 * @param string $batch_code Synthetic unique batch code.
	 */
	private function insert_batch( wpdb $database, string $batch_code ): void {
		self::assertSame(
			1,
			$database->insert(
				$this->tables->batches(),
				array(
					'batch_code'         => $batch_code,
					'tag_type'           => 'classic_tag',
					'model_code'         => 'RT315-MODEL',
					'smart_network'      => 'none',
					'manufacturer'       => 'Synthetic',
					'sales_channel'      => 'direct',
					'requested_quantity' => 1,
					'generated_quantity' => 0,
					'batch_status'       => 'draft',
					'activation_enabled' => 0,
					'notes'              => null,
					'created_by'         => 1,
					'created_at'         => '2026-08-04 00:00:00',
					'updated_at'         => '2026-08-04 00:00:00',
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%s' )
			)
		);
	}

	/**
	 * Insert a synthetic owned Tag and Finder Report.
	 *
	 * @param wpdb $database Database adapter.
	 */
	private function insert_report_fixture( wpdb $database ): int {
		$this->insert_batch( $database, 'RT315-REPORT' );
		$batch_id = (int) $database->insert_id;
		self::assertSame(
			1,
			$database->insert(
				$this->tables->tags(),
				array(
					'tag_id'       => 'N7R2W9',
					'batch_id'     => $batch_id,
					'owner_id'     => 42,
					'tag_type'     => 'classic_tag',
					'model_code'   => 'RT315-MODEL',
					'public_label' => 'Synthetic',
					'tag_status'   => 'active',
					'lost_mode'    => 0,
					'activated_at' => '2026-08-04 00:00:00',
					'created_at'   => '2026-08-04 00:00:00',
					'updated_at'   => '2026-08-04 00:00:00',
				),
				array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
			)
		);
		self::assertSame(
			1,
			$database->insert(
				$this->tables->finder_reports(),
				array(
					'tag_id'                    => 'N7R2W9',
					'owner_id_at_submission'    => 42,
					'message_ciphertext'        => null,
					'report_status'             => 'received',
					'evidence_status'           => 'quarantined',
					'owner_notification_status' => null,
					'owner_notified_at'         => null,
					'expires_at'                => '2026-08-05 00:00:00',
					'created_at'                => '2026-08-04 00:00:00',
					'updated_at'                => '2026-08-04 00:00:00',
				),
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			)
		);

		return (int) $database->insert_id;
	}

	/**
	 * Insert one raw media row for unique-index verification.
	 *
	 * @param wpdb   $database Database adapter.
	 * @param int    $report_id Parent Finder Report identifier.
	 * @param string $digest Synthetic SHA-256 digest.
	 */
	private function insert_media_fixture( wpdb $database, int $report_id, string $digest ): int|false {
		return $database->insert(
			$this->tables->finder_report_media(),
			array(
				'finder_report_id'            => $report_id,
				'object_reference_ciphertext' => 'encrypted-object',
				'encryption_key_id'           => 'test-key',
				'content_sha256'              => $digest,
				'source_mime'                 => 'image/jpeg',
				'source_byte_count'           => 1024,
				'source_width'                => 640,
				'source_height'               => 480,
				'media_status'                => 'quarantined',
				'retention_until'             => '2026-08-05 00:00:00',
				'created_at'                  => '2026-08-04 00:00:00',
				'updated_at'                  => '2026-08-04 00:00:00',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Duplicate one non-sensitive report fixture with a new primary key.
	 *
	 * @param wpdb $database Database adapter.
	 * @param int  $source_report_id Source report identifier.
	 */
	private function insert_duplicate_report( wpdb $database, int $source_report_id ): int {
		$query = $database->prepare(
			'SELECT tag_id, owner_id_at_submission, message_ciphertext, report_status, evidence_status, owner_notification_status, owner_notified_at, expires_at, created_at, updated_at FROM %i WHERE finder_report_id = %d',
			$this->tables->finder_reports(),
			$source_report_id
		);
		$row   = is_string( $query ) ? $database->get_row( $query, ARRAY_A ) : null;
		self::assertIsArray( $row );
		self::assertSame( 1, $database->insert( $this->tables->finder_reports(), $row ) );

		return (int) $database->insert_id;
	}

	/**
	 * Read physical column names.
	 *
	 * @param wpdb   $database Database adapter.
	 * @param string $table Trusted table name.
	 * @return list<string>
	 */
	private function column_names( wpdb $database, string $table ): array {
		$rows = $database->get_col(
			$database->prepare(
				'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
				$table
			)
		);

		return array_values( array_filter( $rows, 'is_string' ) );
	}

	/**
	 * Determine whether one trusted table exists.
	 *
	 * @param wpdb   $database Database adapter.
	 * @param string $table Trusted table name.
	 */
	private function table_exists( wpdb $database, string $table ): bool {
		return 1 === (int) $database->get_var(
			$database->prepare(
				'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
				$table
			)
		);
	}

	/**
	 * Remove trusted ReturnTag tables and Schema state.
	 *
	 * @param wpdb $database Database adapter.
	 */
	private function clear_schema( wpdb $database ): void {
		foreach ( array( $this->tables->finder_report_media(), $this->tables->finder_reports(), $this->tables->events(), $this->tables->access_tokens(), $this->tables->messages(), $this->tables->conversations(), $this->tables->auth_challenges(), $this->tables->batch_exports(), $this->tables->tags(), $this->tables->batches() ) as $table ) {
			$this->drop_table( $database, $table );
		}
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}

	/**
	 * Drop one trusted isolated test table.
	 *
	 * @param wpdb   $database Database adapter.
	 * @param string $table Trusted table name.
	 */
	private function drop_table( wpdb $database, string $table ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated trusted test cleanup.
		$database->query( "DROP TABLE IF EXISTS {$table}" );
	}
}
