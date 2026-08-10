<?php
/**
 * RT-315 Stage 6 Message dispatch migration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Infrastructure\Migration\AddMessageDispatchClaimsMigration;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationReport;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use WP_UnitTestCase;
use wpdb;

/** Verifies additive Schema-11 to Schema-12 behavior. */
final class MessageDispatchMigrationTest extends WP_UnitTestCase {
	/** Remove isolated schema before each test. */
	protected function setUp(): void {
		global $wpdb;
		parent::setUp();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->clear_schema( $wpdb );
	}

	/** Remove isolated schema after each test. */
	protected function tearDown(): void {
		global $wpdb;
		$this->clear_schema( $wpdb );
		parent::tearDown();
	}

	/** Schema 11 data survives the additive upgrade with safe defaults. */
	public function test_eleven_to_twelve_preserves_message_and_sets_dispatch_defaults(): void {
		global $wpdb;
		$this->migrate_directly( $wpdb, 11 );
		$tables = new TableNames( $wpdb->prefix );
		$this->insert_message_fixture( $wpdb, $tables );

		$report = $this->migrate_directly( $wpdb, 12 );
		$row    = $wpdb->get_row( "SELECT * FROM {$tables->messages()} WHERE message_id = 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Isolated fixture and trusted table.

		self::assertSame( 12, $report->ending_version );
		self::assertIsArray( $row );
		self::assertSame( 'opaque-message', $row['body_ciphertext'] );
		self::assertNull( $row['dispatch_claimed_at'] );
		self::assertSame( '0', (string) $row['dispatch_attempt_count'] );
		self::assertTrue( $this->migration( $wpdb )->verify() );
	}

	/** Re-running a complete Schema 12 migration performs no destructive work. */
	public function test_complete_schema_twelve_is_idempotent(): void {
		global $wpdb;
		$this->migrate_directly( $wpdb, 12 );

		$this->migration( $wpdb )->up();

		self::assertTrue( $this->migration( $wpdb )->verify() );
		self::assertSame( 12, ( new WordPressSchemaVersionStore() )->current_version() );
	}

	/**
	 * Insert one synthetic predecessor Message without personal data.
	 *
	 * @param wpdb       $database Database adapter.
	 * @param TableNames $tables Trusted table names.
	 */
	private function insert_message_fixture( wpdb $database, TableNames $tables ): void {
		$database->insert(
			$tables->messages(),
			array(
				'conversation_id'     => 1,
				'sender_role'         => 'owner',
				'body_ciphertext'     => 'opaque-message',
				'delivery_status'     => 'queued',
				'provider_message_id' => null,
				'delivered_at'        => null,
				'created_at'          => '2026-08-10 00:00:00',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Apply one isolated prefix while retaining the failing Migration name.
	 *
	 * @param wpdb $database Database adapter.
	 * @param int  $target Target version.
	 * @throws \RuntimeException When a migration cannot satisfy its postcondition.
	 */
	private function migrate_directly( wpdb $database, int $target ): MigrationReport {
		$store   = new WordPressSchemaVersionStore();
		$start   = $store->current_version();
		$applied = array();
		foreach ( array_slice( ( new MigrationRegistryFactory( $database ) )->create()->all(), 0, $target ) as $migration ) {
			if ( $migration->version() <= $start ) {
				continue;
			}
			try {
				$migration->up();
				if ( ! $migration->verify() ) {
					throw new \RuntimeException( 'Postcondition failed.' );
				}
			} catch ( \Throwable $exception ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test-only diagnostic retains the migration name and chained exception.
				throw new \RuntimeException( $migration->name() . ': ' . $exception->getMessage(), 0, $exception );
			}
			$store->mark_applied( $migration->version() );
			$applied[] = $migration->version();
		}
		return new MigrationReport( $start, $store->current_version(), $applied );
	}

	/**
	 * Return the production Schema 12 Migration.
	 *
	 * @param wpdb $database Database adapter.
	 */
	private function migration( wpdb $database ): AddMessageDispatchClaimsMigration {
		$migration = ( new MigrationRegistryFactory( $database ) )->create()->all()[11];
		self::assertInstanceOf( AddMessageDispatchClaimsMigration::class, $migration );
		return $migration;
	}

	/**
	 * Remove only trusted ReturnTag tables and Schema state.
	 *
	 * @param wpdb $database Database adapter.
	 */
	private function clear_schema( wpdb $database ): void {
		$names = new TableNames( $database->prefix );
		foreach ( array( $names->finder_report_media(), $names->finder_reports(), $names->events(), $names->access_tokens(), $names->messages(), $names->conversations(), $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated cleanup with trusted identifiers.
			$database->query( "DROP TABLE IF EXISTS {$table}" );
		}
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}
}
