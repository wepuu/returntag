<?php
/**
 * RT-329 governance console integration coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Admin\Capability;
use ReturnTag\TagCore\Admin\OperationalRoleProfileCatalog;
use ReturnTag\TagCore\Admin\RetentionTaskManager;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\WordPress\CapabilityInstaller;
use WP_REST_Request;
use WP_UnitTestCase;
use wpdb;

/** Verifies fixed roles, minimized Audit search, and guarded retention status. */
final class AdminGovernanceConsoleTest extends WP_UnitTestCase {
	/**
	 * Administrator fixture ID.
	 *
	 * @var int
	 */
	private int $administrator_id;

	/** Set up the governance schema, roles, and REST server. */
	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->clear_schema( $wpdb );
		$this->migrate( $wpdb );
		$this->administrator_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->administrator_id );
		( new CapabilityInstaller( RETURNTAG_TAGCORE_FILE ) )->install();
		rest_get_server();
	}

	/** Remove the governance fixtures and calibrated role capabilities. */
	protected function tearDown(): void {
		global $wpdb;
		$administrator = get_role( 'administrator' );
		if ( null !== $administrator ) {
			foreach ( ( new OperationalRoleProfileCatalog() )->owned_capabilities() as $capability ) {
				$administrator->remove_cap( $capability );
			}
		}
		foreach ( array_keys( ( new OperationalRoleProfileCatalog() )->profiles() ) as $role_slug ) {
			remove_role( $role_slug );
		}
		delete_option( CapabilityInstaller::OPTION_NAME );
		delete_option( FeatureFlag::ADMIN_RETENTION_RUN->value );
		foreach ( array( 'auth-challenges', 'activation-otp', 'account-otp', 'finder-email', 'finder-evidence' ) as $task_id ) {
			delete_option( 'returntag_admin_retention_run_claim_' . $task_id );
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( RetentionTaskManager::MANUAL_HOOK, array( 'task_id' => $task_id ), RetentionTaskManager::MANUAL_GROUP );
			}
		}
		delete_option( 'returntag_admin_retention_last_run' );
		$this->clear_schema( $wpdb );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/** Verify reinstall preserves unrelated WordPress capabilities and users. */
	public function test_reinstall_calibrates_only_tagcore_owned_role_capabilities(): void {
		$role = get_role( 'returntag_tag_operator' );
		self::assertNotNull( $role );
		$role->add_cap( Capability::MANAGE_RETENTION );
		$role->add_cap( 'upload_files' );
		$user_id = self::factory()->user->create( array( 'role' => 'returntag_tag_operator' ) );

		( new CapabilityInstaller( RETURNTAG_TAGCORE_FILE ) )->install();

		$role = get_role( 'returntag_tag_operator' );
		self::assertNotNull( $role );
		self::assertFalse( $role->has_cap( Capability::MANAGE_RETENTION ) );
		self::assertTrue( $role->has_cap( 'upload_files' ) );
		self::assertContains( 'returntag_tag_operator', get_userdata( $user_id )->roles );
	}

	/** Verify every fixed WordPress role receives only its catalog capabilities. */
	public function test_operational_roles_enforce_the_allow_and_deny_matrix(): void {
		$catalog = new OperationalRoleProfileCatalog();
		foreach ( $catalog->profiles() as $role_slug => $profile ) {
			$user_id = self::factory()->user->create( array( 'role' => $role_slug ) );
			wp_set_current_user( $user_id );
			foreach ( $profile['capabilities'] as $capability ) {
				self::assertTrue( current_user_can( $capability ), $role_slug . ' must receive ' . $capability );
			}
			foreach ( $catalog->owned_capabilities() as $capability ) {
				if ( ! in_array( $capability, $profile['capabilities'], true ) ) {
					self::assertFalse( current_user_can( $capability ), $role_slug . ' must not receive ' . $capability );
				}
			}
			self::assertFalse( current_user_can( 'edit_users' ) );
			self::assertFalse( current_user_can( 'manage_options' ) );
		}
		wp_set_current_user( $this->administrator_id );
	}

	/** Verify audit search excludes metadata and sends private response headers. */
	public function test_audit_search_uses_safe_projection_and_private_headers(): void {
		global $wpdb;
		$tables = new TableNames( $wpdb->prefix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated privacy-projection fixture.
		self::assertSame(
			1,
			$wpdb->insert(
				$tables->events(),
				array(
					'event_type'     => 'tag_suspended',
					'actor_type'     => 'user',
					'actor_id'       => $this->administrator_id,
					'target_type'    => 'tag',
					'target_id'      => '234567',
					'event_result'   => 'success',
					'correlation_id' => 'PRIVATE-CORRELATION',
					'metadata_json'  => '{"private":"PRIVATE-METADATA"}',
					'created_at'     => gmdate( 'Y-m-d H:i:s' ),
				)
			)
		);

		$request = $this->request( 'POST', '/tagcore/v1/admin/audit-events/search' );
		$request->set_body_params(
			array(
				'target_type' => 'tag',
				'target_id'   => '234567',
			)
		);
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertCount( 1, $data['items'] );
		self::assertSame( array( 'event_id', 'event_type', 'actor_type', 'actor_id', 'target_type', 'target_id', 'result', 'created_at', 'actor_user_url' ), array_keys( $data['items'][0] ) );
		$json = wp_json_encode( $data );
		self::assertIsString( $json );
		self::assertStringNotContainsString( 'PRIVATE-', $json );
		self::assertSame( 'no-store, private', $response->get_headers()['Cache-Control'] );
		self::assertSame( 'no-referrer', $response->get_headers()['Referrer-Policy'] );
	}

	/** Verify exact combined filters preserve stable descending keyset pages. */
	public function test_audit_search_combines_exact_filters_with_stable_keysets(): void {
		global $wpdb;
		$tables = new TableNames( $wpdb->prefix );
		foreach ( array( '234567', '234568', '234569' ) as $tag_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated keyset fixture.
			self::assertSame(
				1,
				$wpdb->insert(
					$tables->events(),
					array(
						'event_type'   => 'tag_suspended',
						'actor_type'   => 'user',
						'actor_id'     => $this->administrator_id,
						'target_type'  => 'tag',
						'target_id'    => $tag_id,
						'event_result' => 'success',
						'created_at'   => gmdate( 'Y-m-d H:i:s' ),
					)
				)
			);
		}

		$first = $this->request( 'POST', '/tagcore/v1/admin/audit-events/search' );
		$first->set_body_params(
			array(
				'actor_user_id' => (string) $this->administrator_id,
				'event_type'    => 'tag_suspended',
				'result'        => 'success',
				'per_page'      => 2,
			)
		);
		$first_data = rest_do_request( $first )->get_data();
		self::assertCount( 2, $first_data['items'] );
		self::assertIsString( $first_data['next_cursor'] );

		$second = $this->request( 'POST', '/tagcore/v1/admin/audit-events/search' );
		$second->set_body_params(
			array(
				'actor_user_id' => (string) $this->administrator_id,
				'event_type'    => 'tag_suspended',
				'result'        => 'success',
				'per_page'      => 2,
				'cursor'        => $first_data['next_cursor'],
			)
		);
		$second_data = rest_do_request( $second )->get_data();
		self::assertCount( 1, $second_data['items'] );
		self::assertLessThan( $first_data['items'][1]['event_id'], $second_data['items'][0]['event_id'] );
		self::assertNull( $second_data['next_cursor'] );
	}

	/** Verify retention status is fixed and manual execution fails closed. */
	public function test_retention_status_is_fixed_and_manual_run_flag_fails_closed(): void {
		$response = rest_do_request( $this->request( 'GET', '/tagcore/v1/admin/retention/tasks' ) );
		$data     = $response->get_data();
		self::assertSame( 200, $response->get_status() );
		self::assertCount( 5, $data['items'] );
		self::assertFalse( $data['manual_run_enabled'] );
		$json = wp_json_encode( $data );
		self::assertIsString( $json );
		foreach ( array( 'args', 'object_reference', 'error_message' ) as $forbidden ) {
			self::assertStringNotContainsString( $forbidden, $json );
		}

		$request = $this->request( 'POST', '/tagcore/v1/admin/retention/tasks/activation-otp/run' );
		$request->set_body_params( array( 'confirmation' => 'activation-otp' ) );
		self::assertSame( 400, rest_do_request( $request )->get_status() );
	}

	/** Verify one atomic claim prevents duplicate manual retention requests. */
	public function test_retention_run_queues_once_and_writes_one_metadata_free_request_event(): void {
		self::assertTrue( function_exists( 'as_enqueue_async_action' ) );
		update_option( FeatureFlag::ADMIN_RETENTION_RUN->value, true, false );
		self::assertSame( '1', (string) get_option( FeatureFlag::ADMIN_RETENTION_RUN->value ) );
		self::assertFalse( get_option( 'returntag_admin_retention_run_claim_activation-otp', false ) );
		self::assertFalse( as_has_scheduled_action( RetentionTaskManager::MANUAL_HOOK, array( 'task_id' => 'activation-otp' ), RetentionTaskManager::MANUAL_GROUP ) );
		global $wpdb;
		$tables = new TableNames( $wpdb->prefix );

		$request = $this->request( 'POST', '/tagcore/v1/admin/retention/tasks/activation-otp/run' );
		$request->set_body_params( array( 'confirmation' => 'activation-otp' ) );
		self::assertSame( 202, rest_do_request( $request )->get_status() );
		$duplicate = $this->request( 'POST', '/tagcore/v1/admin/retention/tasks/activation-otp/run' );
		$duplicate->set_body_params( array( 'confirmation' => 'activation-otp' ) );
		self::assertSame( 400, rest_do_request( $duplicate )->get_status() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated audit idempotency assertion.
		$events = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT event_type, actor_type, actor_id, target_type, target_id, event_result, correlation_id, metadata_json FROM %i WHERE event_type = %s',
				$tables->events(),
				'retention_task_run_requested'
			),
			ARRAY_A
		);
		self::assertCount( 1, $events );
		self::assertSame( 'user', $events[0]['actor_type'] );
		self::assertSame( (string) $this->administrator_id, (string) $events[0]['actor_id'] );
		self::assertSame( 'retention_task', $events[0]['target_type'] );
		self::assertSame( 'activation_cleanup', $events[0]['target_id'] );
		self::assertSame( 'queued', $events[0]['event_result'] );
		self::assertNull( $events[0]['correlation_id'] );
		self::assertNull( $events[0]['metadata_json'] );
	}

	/** Verify governance routes require both nonce and explicit capabilities. */
	public function test_governance_routes_reject_missing_capability_and_nonce(): void {
		$request = new WP_REST_Request( 'POST', '/tagcore/v1/admin/audit-events/search' );
		self::assertSame( 403, rest_do_request( $request )->get_status() );
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
		self::assertSame( 403, rest_do_request( $this->request( 'GET', '/tagcore/v1/admin/retention/tasks' ) )->get_status() );
	}

	/** Verify governance data fails closed while Schema preparation is stale. */
	public function test_governance_routes_reject_an_old_schema(): void {
		update_option( WordPressSchemaVersionStore::OPTION_NAME, 13, false );
		self::assertSame( 503, rest_do_request( $this->request( 'GET', '/tagcore/v1/admin/retention/tasks' ) )->get_status() );
	}

	/**
	 * Build an authenticated REST request.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  REST route.
	 */
	private function request( string $method, string $route ): WP_REST_Request {
		$request = new WP_REST_Request( $method, $route );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		return $request;
	}

	/**
	 * Migrate the isolated test schema to the current version.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function migrate( wpdb $database ): void {
		$runner = new MigrationRunner( ( new MigrationRegistryFactory( $database ) )->create(), new WordPressSchemaVersionStore(), new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 ) );
		self::assertSame( 16, $runner->migrate()->ending_version );
	}

	/**
	 * Remove the isolated TagCore test schema.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function clear_schema( wpdb $database ): void {
		$tables = new TableNames( $database->prefix );
		foreach ( array( $tables->tag_transfers(), $tables->finder_report_media(), $tables->finder_reports(), $tables->events(), $tables->access_tokens(), $tables->messages(), $tables->conversations(), $tables->auth_challenges(), $tables->batch_exports(), $tables->tags(), $tables->batches() ) as $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated trusted-table cleanup.
			$database->query( "DROP TABLE IF EXISTS {$table_name}" );
		}
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}
}
