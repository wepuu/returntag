<?php
/**
 * RT-340 authentication challenge retention integration coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAdminGovernanceReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAuthChallengeRetentionStore;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Queue\AuthChallengeRetentionBootstrap;
use WP_UnitTestCase;
use wpdb;

/** Verifies purpose-independent, private-field-free deletion on the real schema. */
final class AuthChallengeRetentionTest extends WP_UnitTestCase {
	/** Install Schema 16 for each isolated test. */
	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->clear_schema( $wpdb );
		$runner = new MigrationRunner( ( new MigrationRegistryFactory( $wpdb ) )->create(), new WordPressSchemaVersionStore(), new WordPressAdvisoryMigrationLock( $wpdb, get_current_blog_id(), 0 ) );
		self::assertSame( 16, $runner->migrate()->ending_version );
	}

	/** Remove fixtures and recurring actions. */
	protected function tearDown(): void {
		global $wpdb;
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( AuthChallengeRetentionBootstrap::CLEANUP_HOOK, array(), AuthChallengeRetentionBootstrap::CLEANUP_GROUP );
		}
		$this->clear_schema( $wpdb );
		parent::tearDown();
	}

	/** Expired or consumed rows of every purpose are eligible; open rows are not. */
	public function test_cleanup_is_purpose_independent_and_bounded(): void {
		global $wpdb;
		$now    = new DateTimeImmutable( '2026-08-27 12:00:00', new DateTimeZone( 'UTC' ) );
		$tables = new TableNames( $wpdb->prefix );

		$this->insert_challenge( $wpdb, $tables, 'activation_otp', '2026-08-27 10:00:00', null );
		$this->insert_challenge( $wpdb, $tables, 'finder_email_otp', '2026-08-27 11:00:00', null );
		$this->insert_challenge( $wpdb, $tables, 'account_otp', '2026-08-28 12:00:00', '2026-08-27 11:30:00' );
		$this->insert_challenge( $wpdb, $tables, 'account_otp', '2026-08-28 12:00:00', null );
		$this->insert_challenge( $wpdb, $tables, 'finder_email_otp', '2026-08-28 12:00:00', '2026-08-28 11:00:00' );

		$gateway = new WpdbGateway( $wpdb );
		$store   = new WpdbAuthChallengeRetentionStore( $gateway, $tables, new DatabaseDateTimeCodec() );
		self::assertSame( 3, ( new WpdbAdminGovernanceReader( $gateway, $tables ) )->retention_backlog( 'auth-challenges', '2026-08-27 12:00:00' ) );
		self::assertSame( 2, $store->cleanup_eligible( $now, 2 ) );
		self::assertSame( 1, $store->cleanup_eligible( $now, 2 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Isolated retention projection assertion.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT purpose, consumed_at FROM %i ORDER BY challenge_id ASC', $tables->auth_challenges() ), ARRAY_A );
		self::assertSame(
			array(
				array(
					'purpose'     => 'account_otp',
					'consumed_at' => null,
				),
				array(
					'purpose'     => 'finder_email_otp',
					'consumed_at' => '2026-08-28 11:00:00',
				),
			),
			$rows
		);
	}

	/** The composition root exposes an hourly recurring maintenance action. */
	public function test_registers_one_hourly_recurring_action(): void {
		self::assertSame( 3600, AuthChallengeRetentionBootstrap::INTERVAL );
		AuthChallengeRetentionBootstrap::register();
		do_action( 'action_scheduler_init' );

		self::assertNotFalse( as_has_scheduled_action( AuthChallengeRetentionBootstrap::CLEANUP_HOOK, array(), AuthChallengeRetentionBootstrap::CLEANUP_GROUP ) );
	}

	/**
	 * Insert one syntactically valid, non-secret challenge fixture.
	 *
	 * @param wpdb        $database Active test database.
	 * @param TableNames  $tables Trusted table names.
	 * @param string      $purpose Fixed challenge purpose.
	 * @param string      $expires_at UTC expiry.
	 * @param string|null $consumed_at Optional UTC consumption time.
	 */
	private function insert_challenge( wpdb $database, TableNames $tables, string $purpose, string $expires_at, ?string $consumed_at ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated retention fixture.
		self::assertSame(
			1,
			$database->insert(
				$tables->auth_challenges(),
				array(
					'purpose'          => $purpose,
					'subject_type'     => 'retention_fixture',
					'subject_id'       => 'fixture-' . wp_generate_uuid4(),
					'email_ciphertext' => 'fixture-ciphertext',
					'email_lookup'     => str_repeat( 'a', 64 ),
					'code_hash'        => '$2y$10$fixture-not-a-real-credential',
					'attempt_count'    => 0,
					'send_count'       => 1,
					'ip_hash'          => str_repeat( 'b', 64 ),
					'expires_at'       => $expires_at,
					'verified_at'      => null,
					'consumed_at'      => $consumed_at,
					'created_at'       => '2026-08-27 09:00:00',
				)
			)
		);
	}

	/**
	 * Remove every TagCore table used by the Schema 16 migration chain.
	 *
	 * @param wpdb $database Active test database.
	 */
	private function clear_schema( wpdb $database ): void {
		$tables = new TableNames( $database->prefix );
		foreach ( array( $tables->privacy_requests(), $tables->email_webhook_events(), $tables->email_deliveries(), $tables->tag_transfers(), $tables->finder_report_media(), $tables->finder_reports(), $tables->events(), $tables->access_tokens(), $tables->messages(), $tables->conversations(), $tables->auth_challenges(), $tables->batch_exports(), $tables->tags(), $tables->batches() ) as $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated trusted-table cleanup.
			$database->query( "DROP TABLE IF EXISTS {$table_name}" );
		}
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}
}
