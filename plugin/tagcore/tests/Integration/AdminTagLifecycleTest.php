<?php
/**
 * RT-327 administrator Tag lifecycle integration coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Admin\Capability;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\WordPress\CapabilityInstaller;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;
use wpdb;

/** Verifies the four atomic actions, revocation, audit, and failure gates. */
final class AdminTagLifecycleTest extends WP_UnitTestCase {
	/**
	 * Authorized administrator fixture.
	 *
	 * @var int
	 */
	private int $administrator_id;

	/**
	 * Current Owner fixture.
	 *
	 * @var int
	 */
	private int $owner_id;

	/**
	 * Transfer target fixture.
	 *
	 * @var int
	 */
	private int $target_id;

	/**
	 * Manufacturing Batch fixture.
	 *
	 * @var int
	 */
	private int $batch_id;

	/** Prepare current Schema, capabilities, flag, and Users. */
	protected function setUp(): void {
		parent::setUp();
		global $wpdb;

		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->clear_schema( $wpdb );
		$this->migrate( $wpdb );
		$this->administrator_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->owner_id         = self::factory()->user->create( array( 'user_email' => 'rt327-owner-' . $this->administrator_id . '@example.test' ) );
		$this->target_id        = self::factory()->user->create( array( 'user_email' => 'rt327-target-' . $this->administrator_id . '@example.test' ) );
		wp_set_current_user( $this->administrator_id );
		( new CapabilityInstaller( RETURNTAG_TAGCORE_FILE ) )->install();
		update_option( FeatureFlag::ADMIN_TAG_LIFECYCLE->value, true, false );
		rest_get_server();
		$this->batch_id = $this->insert_batch( $wpdb );
	}

	/** Remove isolated Schema, capabilities, and flags. */
	protected function tearDown(): void {
		global $wpdb;
		$role = get_role( 'administrator' );
		if ( null !== $role ) {
			foreach ( array(
				Capability::MANAGE_RETURNTAG,
				Capability::MANAGE_BATCHES,
				Capability::MANAGE_TAGS,
				Capability::MANAGE_TAG_LIFECYCLE,
				Capability::MANAGE_DISPUTES,
				Capability::VIEW_USERS,
				Capability::VIEW_AUDIT_LOGS,
			) as $capability ) {
				$role->remove_cap( $capability );
			}
		}
		delete_option( CapabilityInstaller::OPTION_NAME );
		delete_option( FeatureFlag::ADMIN_TAG_LIFECYCLE->value );
		$this->clear_schema( $wpdb );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/** All actions converge to committed state and write one bounded Event each. */
	public function test_four_actions_apply_the_frozen_state_matrix(): void {
		global $wpdb;
		$this->insert_tag( $wpdb, '234567', 'active', $this->owner_id );
		$this->insert_tag( $wpdb, '234568', 'suspended', $this->owner_id );
		$this->insert_tag( $wpdb, '234569', 'active', $this->owner_id );
		$this->insert_tag( $wpdb, '23456A', 'active', $this->owner_id );

		$cases = array(
			array( '234567', 'suspend', 'active', $this->owner_id, null, 'suspended', $this->owner_id ),
			array( '234568', 'retire', 'suspended', $this->owner_id, null, 'retired', $this->owner_id ),
			array( '234569', 'remove-owner', 'active', $this->owner_id, null, 'suspended', null ),
			array( '23456A', 'transfer-owner', 'active', $this->owner_id, $this->target_id, 'active', $this->target_id ),
		);

		foreach ( $cases as $case ) {
			list( $tag_id, $action, $before_status, $before_owner, $target, $after_status, $after_owner ) = $case;
			$body = array(
				'confirmation'      => $tag_id,
				'expected_status'   => $before_status,
				'expected_owner_id' => $before_owner,
			);
			if ( null !== $target ) {
				$body['target_user_id'] = $target;
			}
			$response = rest_do_request( $this->post( "/tagcore/v1/admin/tags/{$tag_id}/{$action}", $body ) );
			self::assertSame( 200, $response->get_status() );
			self::assertSame( $after_status, $response->get_data()['tag_status'] );
			self::assertSame( $after_owner, $response->get_data()['owner_id'] );
			$this->assert_private_headers( $response );
		}

		$tables       = new TableNames( $wpdb->prefix );
		$events_table = $tables->events();
		$rows         = $wpdb->get_results( "SELECT event_type, actor_id, metadata_json FROM {$events_table} ORDER BY event_id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated trusted-table assertion.
		self::assertSame( array( 'tag_suspended', 'tag_retired', 'tag_owner_removed', 'tag_transferred' ), array_column( $rows, 'event_type' ) );
		foreach ( $rows as $row ) {
			self::assertSame( (string) $this->administrator_id, (string) $row['actor_id'] );
			self::assertStringContainsString( 'before_status', (string) $row['metadata_json'] );
			self::assertStringNotContainsString( '@', (string) $row['metadata_json'] );
		}
	}

	/** Transfer revokes prior access, delivery, notifications, and pending invitations atomically. */
	public function test_transfer_revokes_every_prior_owner_access_path(): void {
		global $wpdb;
		$tag_id = '23456B';
		$this->insert_tag( $wpdb, $tag_id, 'active', $this->owner_id );
		$ids = $this->insert_access_fixtures( $wpdb, $tag_id );

		$response = rest_do_request(
			$this->post(
				"/tagcore/v1/admin/tags/{$tag_id}/transfer-owner",
				array(
					'confirmation'      => $tag_id,
					'expected_status'   => 'active',
					'expected_owner_id' => $this->owner_id,
					'target_user_id'    => $this->target_id,
				)
			)
		);
		self::assertSame( 200, $response->get_status() );

		$tables = new TableNames( $wpdb->prefix );
		self::assertSame( 'closed', $wpdb->get_var( $wpdb->prepare( 'SELECT conversation_status FROM %i WHERE conversation_id=%d', $tables->conversations(), $ids['conversation'] ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Isolated assertion.
		self::assertSame( 'failed', $wpdb->get_var( $wpdb->prepare( 'SELECT delivery_status FROM %i WHERE message_id=%d', $tables->messages(), $ids['message'] ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Isolated assertion.
		self::assertNotNull( $wpdb->get_var( $wpdb->prepare( 'SELECT revoked_at FROM %i WHERE token_id=%d', $tables->access_tokens(), $ids['token'] ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Isolated assertion.
		self::assertSame( 'cancelled', $wpdb->get_var( $wpdb->prepare( 'SELECT transfer_status FROM %i WHERE transfer_id=%d', $tables->tag_transfers(), $ids['transfer'] ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Isolated assertion.
		self::assertSame( 'failed', $wpdb->get_var( $wpdb->prepare( 'SELECT owner_notification_status FROM %i WHERE finder_report_id=%d', $tables->finder_reports(), $ids['report'] ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Isolated assertion.
	}

	/** Nonce, capability, flag, exact confirmation, and stale state all fail closed. */
	public function test_security_and_stale_state_gates_fail_closed(): void {
		global $wpdb;
		$tag_id = '23456C';
		$this->insert_tag( $wpdb, $tag_id, 'active', $this->owner_id );
		$route = "/tagcore/v1/admin/tags/{$tag_id}/suspend";
		$body  = array(
			'confirmation'      => $tag_id,
			'expected_status'   => 'active',
			'expected_owner_id' => $this->owner_id,
		);

		$missing_nonce = new WP_REST_Request( 'POST', $route );
		$missing_nonce->set_body_params( $body );
		self::assertSame( 403, rest_do_request( $missing_nonce )->get_status() );

		update_option( FeatureFlag::ADMIN_TAG_LIFECYCLE->value, false, false );
		self::assertSame( 409, rest_do_request( $this->post( $route, $body ) )->get_status() );
		update_option( FeatureFlag::ADMIN_TAG_LIFECYCLE->value, true, false );

		$body['confirmation'] = '234567';
		self::assertSame( 409, rest_do_request( $this->post( $route, $body ) )->get_status() );
		$body['confirmation']      = $tag_id;
		$body['expected_owner_id'] = $this->target_id;
		self::assertSame( 409, rest_do_request( $this->post( $route, $body ) )->get_status() );

		get_role( 'administrator' )?->remove_cap( Capability::MANAGE_TAG_LIFECYCLE );
		wp_get_current_user()->get_role_caps();
		self::assertSame( 403, rest_do_request( $this->post( $route, $body ) )->get_status() );
	}

	/**
	 * Create one authenticated REST POST request.
	 *
	 * @param string               $route Internal REST route.
	 * @param array<string, mixed> $body Request body.
	 */
	private function post( string $route, array $body ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params( $body );
		return $request;
	}

	/**
	 * Assert the private response controls.
	 *
	 * @param WP_REST_Response $response REST response.
	 */
	private function assert_private_headers( WP_REST_Response $response ): void {
		self::assertSame( 'no-store, private', $response->get_headers()['Cache-Control'] );
		self::assertSame( 'no-referrer', $response->get_headers()['Referrer-Policy'] );
		self::assertSame( 'nosniff', $response->get_headers()['X-Content-Type-Options'] );
	}

	/**
	 * Insert one released Batch fixture.
	 *
	 * @param wpdb $database Isolated WordPress database.
	 */
	private function insert_batch( wpdb $database ): int {
		$tables = new TableNames( $database->prefix );
		$database->insert(
			$tables->batches(),
			array(
				'batch_code'         => 'RT-327-LIFECYCLE',
				'tag_type'           => 'classic_tag',
				'smart_network'      => 'none',
				'requested_quantity' => 8,
				'generated_quantity' => 8,
				'batch_status'       => 'released',
				'activation_enabled' => 1,
				'created_by'         => $this->administrator_id,
				'created_at'         => '2026-08-17 08:00:00',
				'updated_at'         => '2026-08-17 08:00:00',
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated fixture.
		return (int) $database->insert_id;
	}

	/**
	 * Insert one Tag fixture.
	 *
	 * @param wpdb     $database Isolated WordPress database.
	 * @param string   $tag_id Canonical Tag ID.
	 * @param string   $status Canonical Tag status.
	 * @param int|null $owner_id Nullable Owner User ID.
	 */
	private function insert_tag( wpdb $database, string $tag_id, string $status, ?int $owner_id ): void {
		$tables = new TableNames( $database->prefix );
		self::assertSame(
			1,
			$database->insert(
				$tables->tags(),
				array(
					'tag_id'       => $tag_id,
					'batch_id'     => $this->batch_id,
					'owner_id'     => $owner_id,
					'tag_type'     => 'classic_tag',
					'tag_status'   => $status,
					'lost_mode'    => 0,
					'activated_at' => null === $owner_id ? null : '2026-08-17 08:00:00',
					'created_at'   => '2026-08-17 08:00:00',
					'updated_at'   => '2026-08-17 08:00:00',
				)
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated fixture.
	}

	/**
	 * Insert revocable conversation, delivery, transfer, and report fixtures.
	 *
	 * @param wpdb   $database Isolated WordPress database.
	 * @param string $tag_id Canonical Tag ID.
	 * @return array{conversation:int,message:int,token:int,transfer:int,report:int}
	 */
	private function insert_access_fixtures( wpdb $database, string $tag_id ): array {
		$tables = new TableNames( $database->prefix );
		$database->insert(
			$tables->conversations(),
			array(
				'tag_id'                  => $tag_id,
				'owner_id_snapshot'       => $this->owner_id,
				'finder_email_ciphertext' => 'ciphertext',
				'finder_email_lookup'     => str_repeat( 'a', 64 ),
				'conversation_status'     => 'open',
				'expires_at'              => '2026-09-17 08:00:00',
				'last_activity_at'        => '2026-08-17 08:00:00',
				'created_at'              => '2026-08-17 08:00:00',
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated fixture.
		$conversation = (int) $database->insert_id;
		$database->insert(
			$tables->messages(),
			array(
				'conversation_id' => $conversation,
				'sender_role'     => 'owner',
				'body_ciphertext' => 'private-ciphertext',
				'delivery_status' => 'queued',
				'created_at'      => '2026-08-17 08:00:00',
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated fixture.
		$message = (int) $database->insert_id;
		$database->insert(
			$tables->access_tokens(),
			array(
				'conversation_id' => $conversation,
				'purpose'         => 'conversation_access',
				'actor_role'      => 'owner',
				'token_hash'      => str_repeat( 'b', 64 ),
				'expires_at'      => '2026-09-17 08:00:00',
				'created_at'      => '2026-08-17 08:00:00',
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated fixture.
		$token = (int) $database->insert_id;
		$database->insert(
			$tables->tag_transfers(),
			array(
				'tag_id'                  => $tag_id,
				'from_owner_id'           => $this->owner_id,
				'target_email_ciphertext' => 'encrypted',
				'target_email_lookup'     => str_repeat( 'c', 64 ),
				'transfer_status'         => 'pending',
				'invitation_status'       => 'pending',
				'expires_at'              => '2026-09-17 08:00:00',
				'created_at'              => '2026-08-17 08:00:00',
				'updated_at'              => '2026-08-17 08:00:00',
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated fixture.
		$transfer = (int) $database->insert_id;
		$database->insert(
			$tables->finder_reports(),
			array(
				'tag_id'                    => $tag_id,
				'owner_id_at_submission'    => $this->owner_id,
				'report_status'             => 'ready',
				'evidence_status'           => 'ready',
				'owner_notification_status' => 'queued',
				'expires_at'                => '2026-09-17 08:00:00',
				'created_at'                => '2026-08-17 08:00:00',
				'updated_at'                => '2026-08-17 08:00:00',
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated fixture.
		return array(
			'conversation' => $conversation,
			'message'      => $message,
			'token'        => $token,
			'transfer'     => $transfer,
			'report'       => (int) $database->insert_id,
		);
	}

	/**
	 * Apply the production Schema chain.
	 *
	 * @param wpdb $database Isolated WordPress database.
	 */
	private function migrate( wpdb $database ): void {
		$runner = new MigrationRunner( ( new MigrationRegistryFactory( $database ) )->create(), new WordPressSchemaVersionStore(), new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 ) );
		self::assertSame( 16, $runner->migrate()->ending_version );
	}

	/**
	 * Drop only trusted ReturnTag tables from the isolated database.
	 *
	 * @param wpdb $database Isolated WordPress database.
	 */
	private function clear_schema( wpdb $database ): void {
		$tables = new TableNames( $database->prefix );
		foreach ( array( $tables->tag_transfers(), $tables->finder_report_media(), $tables->finder_reports(), $tables->events(), $tables->access_tokens(), $tables->messages(), $tables->conversations(), $tables->auth_challenges(), $tables->batch_exports(), $tables->tags(), $tables->batches() ) as $table_name ) {
			$database->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated trusted-table cleanup.
		}
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}
}
