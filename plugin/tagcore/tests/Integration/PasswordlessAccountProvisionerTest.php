<?php
/**
 * Passwordless WordPress account integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use ReturnTag\TagCore\Application\Auth\PasswordlessAccountEventIdentityPolicy;
use ReturnTag\TagCore\Application\Persistence\DenyAllEventMetadataPolicy;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Infrastructure\Auth\WordPressPasswordlessAccountProvisioner;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbEventRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use RuntimeException;
use WP_UnitTestCase;
use wpdb;

/**
 * Verifies account reuse, least privilege, and audit repair.
 */
final class PasswordlessAccountProvisionerTest extends WP_UnitTestCase {
	/**
	 * User IDs created by the provisioner and requiring explicit cleanup.
	 *
	 * @var list<int>
	 */
	private array $created_user_ids = array();

	/**
	 * Prepare the current custom Schema.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		self::assertInstanceOf( wpdb::class, $wpdb );

		$this->clear_schema( $wpdb );
		$runner = new MigrationRunner(
			( new MigrationRegistryFactory( $wpdb ) )->create(),
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $wpdb, get_current_blog_id(), 0 )
		);
		self::assertSame( 15, $runner->migrate()->ending_version );
	}

	/**
	 * Remove only isolated test users and custom tables.
	 */
	protected function tearDown(): void {
		global $wpdb;

		foreach ( $this->created_user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}

		$this->clear_schema( $wpdb );
		parent::tearDown();
	}

	/**
	 * An existing user is reused without changing credentials or role.
	 */
	public function test_existing_user_is_reused_without_password_or_role_change(): void {
		$user_id        = self::factory()->user->create(
			array(
				'user_email' => 'existing@example.test',
				'role'       => 'editor',
			)
		);
		$before         = get_userdata( $user_id );
		$user_count     = count_users()['total_users'];
		$provisioner    = $this->provisioner();
		$provisioned_id = $provisioner->provision(
			new EmailAddress( 'existing@example.test' ),
			$this->lookup(),
			$this->now()
		);
		$after          = get_userdata( $user_id );

		self::assertSame( $user_id, $provisioned_id );
		self::assertSame( $user_count, count_users()['total_users'] );
		self::assertSame( $before?->user_pass, $after?->user_pass );
		self::assertContains( 'editor', $after?->roles ?? array() );
		self::assertSame(
			'',
			(string) get_user_meta( $user_id, WordPressPasswordlessAccountProvisioner::SOURCE_META, true )
		);
	}

	/**
	 * Duplicate exact email records fail closed instead of selecting an account.
	 */
	public function test_ambiguous_duplicate_email_fails_closed(): void {
		global $wpdb;

		foreach ( array( 'first', 'second' ) as $suffix ) {
			// Deliberately create invalid duplicate core data for fail-closed acceptance.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$inserted = $wpdb->insert(
				$wpdb->users,
				array(
					'user_login'      => 'returntag_duplicate_' . $suffix,
					'user_pass'       => wp_hash_password( wp_generate_password( 48, true, true ) ),
					'user_nicename'   => 'returntag-duplicate-' . $suffix,
					'user_email'      => 'duplicate@example.test',
					'user_registered' => '2026-07-30 12:00:00',
					'display_name'    => 'Duplicate Fixture',
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			self::assertSame( 1, $inserted );
			$this->created_user_ids[] = (int) $wpdb->insert_id;
		}

		$this->expectException( RuntimeException::class );
		$this->provisioner()->provision(
			new EmailAddress( 'duplicate@example.test' ),
			$this->lookup(),
			$this->now()
		);
	}

	/**
	 * A new email receives one opaque least-privilege account and one audit.
	 */
	public function test_new_user_is_created_once_and_audited_once(): void {
		global $wpdb;

		$before      = count_users()['total_users'];
		$provisioner = $this->provisioner();
		$email       = new EmailAddress( 'new-owner@example.test' );
		$user_id     = $provisioner->provision( $email, $this->lookup(), $this->now() );

		$this->created_user_ids[] = $user_id;
		$user                     = get_userdata( $user_id );
		$repeat_id                = $provisioner->provision( $email, $this->lookup(), $this->now() );
		$tables                   = new TableNames( $wpdb->prefix );

		// Isolated audit acceptance query.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$event_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE event_type = %s AND target_type = %s AND target_id = %s',
				$tables->events(),
				PasswordlessAccountEventIdentityPolicy::ACCOUNT_CREATED,
				'user',
				(string) $user_id
			)
		);

		self::assertSame( $user_id, $repeat_id );
		self::assertSame( $before + 1, count_users()['total_users'] );
		self::assertNotFalse( $user );
		self::assertSame( $email->value, $user?->user_email );
		self::assertStringStartsWith( 'returntag_', $user?->user_login ?? '' );
		self::assertStringNotContainsString( 'new-owner', $user?->user_login ?? '' );
		self::assertContains( 'subscriber', $user?->roles ?? array() );
		self::assertSame(
			'1',
			(string) get_user_meta( $user_id, WordPressPasswordlessAccountProvisioner::SOURCE_META, true )
		);
		self::assertGreaterThan(
			0,
			(int) get_user_meta( $user_id, WordPressPasswordlessAccountProvisioner::AUDIT_EVENT_META, true )
		);
		self::assertSame( 1, $event_count );
	}

	/**
	 * A prior account-creation failure repairs its missing audit before reuse.
	 */
	public function test_retry_repairs_missing_account_audit(): void {
		global $wpdb;

		$user_id = self::factory()->user->create(
			array(
				'user_email' => 'repair@example.test',
				'meta_input' => array(
					WordPressPasswordlessAccountProvisioner::SOURCE_META => 1,
				),
			)
		);

		self::assertSame(
			$user_id,
			$this->provisioner()->provision(
				new EmailAddress( 'repair@example.test' ),
				$this->lookup(),
				$this->now()
			)
		);

		$tables = new TableNames( $wpdb->prefix );
		// Isolated audit recovery assertion.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE event_type = %s AND target_id = %s',
				$tables->events(),
				PasswordlessAccountEventIdentityPolicy::ACCOUNT_CREATED,
				(string) $user_id
			)
		);

		self::assertSame( 1, $count );
		self::assertGreaterThan(
			0,
			(int) get_user_meta( $user_id, WordPressPasswordlessAccountProvisioner::AUDIT_EVENT_META, true )
		);
	}

	/**
	 * Build the production account adapter against the isolated Schema.
	 */
	private function provisioner(): WordPressPasswordlessAccountProvisioner {
		global $wpdb;

		$tables = new TableNames( $wpdb->prefix );
		$events = new WpdbEventRepository(
			new WpdbGateway( $wpdb ),
			$tables,
			new DatabaseDateTimeCodec(),
			new DenyAllEventMetadataPolicy(),
			new PasswordlessAccountEventIdentityPolicy()
		);

		return new WordPressPasswordlessAccountProvisioner( $wpdb, $events );
	}

	/**
	 * Return one stable keyed lookup fixture.
	 */
	private function lookup(): LookupDigest {
		return LookupDigest::from_digest( str_repeat( 'b', 64 ) );
	}

	/**
	 * Return one UTC audit time.
	 */
	private function now(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-07-30 12:00:00', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Remove only isolated custom Schema state.
	 *
	 * @param wpdb $database Active test database connection.
	 */
	private function clear_schema( wpdb $database ): void {
		$names = new TableNames( $database->prefix );

		$tables = array(
			$names->events(),
			$names->access_tokens(),
			$names->messages(),
			$names->conversations(),
			$names->auth_challenges(),
			$names->batch_exports(),
			$names->tags(),
			$names->batches(),
		);

		foreach ( $tables as $table_name ) {
			// Isolated cleanup with trusted identifiers.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$database->query( "DROP TABLE IF EXISTS {$table_name}" );
		}

		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}
}
