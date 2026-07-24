<?php
/**
 * RT-201 Batch administration integration coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Admin\Capability;
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

/**
 * Exercises capabilities, REST boundaries, persistence, and audit Events.
 */
final class BatchAdminTest extends WP_UnitTestCase {
	/**
	 * Authorized administrator fixture.
	 *
	 * @var int
	 */
	private int $administrator_id;

	/**
	 * Prepare an isolated current Schema and capability contract.
	 */
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

	/**
	 * Remove only isolated ReturnTag fixtures and installed test capabilities.
	 */
	protected function tearDown(): void {
		global $wpdb;

		$role = get_role( 'administrator' );

		if ( null !== $role ) {
			$role->remove_cap( Capability::MANAGE_RETURNTAG );
			$role->remove_cap( Capability::MANAGE_BATCHES );
		}

		delete_option( CapabilityInstaller::OPTION_NAME );
		$this->clear_schema( $wpdb );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Capability installation must be versioned, non-autoloaded, and idempotent.
	 */
	public function test_capability_installer_is_versioned_and_idempotent(): void {
		global $wpdb;

		$installer = new CapabilityInstaller( RETURNTAG_TAGCORE_FILE );
		$installer->install();

		self::assertTrue( current_user_can( Capability::MANAGE_RETURNTAG ) );
		self::assertTrue( current_user_can( Capability::MANAGE_BATCHES ) );
		self::assertSame( 1, get_option( CapabilityInstaller::OPTION_NAME ) );
		self::assertArrayNotHasKey( CapabilityInstaller::OPTION_NAME, wp_load_alloptions() );

		$query = $wpdb->prepare(
			"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
			CapabilityInstaller::OPTION_NAME
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Trusted WordPress options table; prepared Option name.
		self::assertNotContains( $wpdb->get_var( $query ), array( 'yes', 'on', 'auto-on', 'auto' ) );
	}

	/**
	 * Unauthorized users cannot list, inspect, or create Batches.
	 */
	public function test_routes_require_dedicated_capability(): void {
		wp_set_current_user( 0 );

		foreach (
			array(
				new WP_REST_Request( 'GET', '/tagcore/v1/batches' ),
				new WP_REST_Request( 'GET', '/tagcore/v1/batches/1' ),
				new WP_REST_Request( 'POST', '/tagcore/v1/batches' ),
			) as $request
		) {
			self::assertSame( 403, rest_do_request( $request )->get_status() );
		}
	}

	/**
	 * Administrative routes must fail closed while the product Schema is stale.
	 */
	public function test_routes_fail_closed_when_schema_is_not_current(): void {
		update_option( WordPressSchemaVersionStore::OPTION_NAME, 7, false );

		$request  = new WP_REST_Request( 'GET', '/tagcore/v1/batches' );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		self::assertSame( 503, $response->get_status() );
		self::assertIsArray( $data );
		self::assertSame( 'returntag_schema_unavailable', $data['code'] );
		$this->assert_no_store_after_serving( $response, $request );
	}

	/**
	 * Create must ignore client-controlled state and append one audit Event.
	 */
	public function test_create_persists_server_controlled_draft_and_event(): void {
		global $wpdb;

		$response = rest_do_request( $this->create_request( 'RT-201-001' ) );
		$data     = $response->get_data();

		self::assertSame( 201, $response->get_status() );
		self::assertIsArray( $data );
		self::assertSame( 'RT-201-001', $data['batch_code'] );
		self::assertSame( 'draft', $data['batch_status'] );
		self::assertSame( 0, $data['generated_quantity'] );
		self::assertFalse( $data['activation_enabled'] );
		self::assertSame( $this->administrator_id, $data['created_by'] );
		self::assertSame( 'no-store, private', $response->get_headers()['Cache-Control'] );

		$tables = new TableNames( $wpdb->prefix );
		$query  = $wpdb->prepare(
			'SELECT batch_status, generated_quantity, activation_enabled, created_by FROM %i WHERE batch_id = %d',
			$tables->batches(),
			$data['batch_id']
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Verifying the stored RT-201 contract.
		$row = $wpdb->get_row( $query, ARRAY_A );

		self::assertIsArray( $row );
		self::assertSame( 'draft', $row['batch_status'] );
		self::assertSame( '0', $row['generated_quantity'] );
		self::assertSame( '0', $row['activation_enabled'] );
		self::assertSame( (string) $this->administrator_id, $row['created_by'] );

		$event_query = $wpdb->prepare(
			'SELECT event_type, actor_type, actor_id, target_type, target_id, event_result, correlation_id, metadata_json FROM %i WHERE target_type = %s AND target_id = %s',
			$tables->events(),
			'batch',
			(string) $data['batch_id']
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Verifying the append-only audit Event.
		$event = $wpdb->get_row( $event_query, ARRAY_A );

		self::assertIsArray( $event );
		self::assertSame( 'batch.created', $event['event_type'] );
		self::assertSame( 'user', $event['actor_type'] );
		self::assertSame( (string) $this->administrator_id, $event['actor_id'] );
		self::assertSame( 'batch', $event['target_type'] );
		self::assertSame( 'success', $event['event_result'] );
		self::assertNull( $event['correlation_id'] );
		self::assertNull( $event['metadata_json'] );
	}

	/**
	 * Duplicate Batch Codes return a field-safe conflict without another Event.
	 */
	public function test_duplicate_batch_code_returns_conflict_without_partial_write(): void {
		global $wpdb;

		self::assertSame( 201, rest_do_request( $this->create_request( 'RT-201-DUPLICATE' ) )->get_status() );
		$request  = $this->create_request( 'RT-201-DUPLICATE' );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		self::assertSame( 409, $response->get_status() );
		self::assertIsArray( $data );
		self::assertSame( 'returntag_batch_code_conflict', $data['code'] );
		self::assertArrayHasKey( 'batch_code', $data['data']['fields'] );
		$this->assert_no_store_after_serving( $response, $request );

		$tables = new TableNames( $wpdb->prefix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted isolated test table.
		self::assertSame( '1', $wpdb->get_var( "SELECT COUNT(*) FROM {$tables->batches()}" ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted isolated test table.
		self::assertSame( '1', $wpdb->get_var( "SELECT COUNT(*) FROM {$tables->events()}" ) );
	}

	/**
	 * Collection and item endpoints expose bounded safe projections.
	 */
	public function test_list_and_detail_return_created_batch(): void {
		$created_response = rest_do_request( $this->create_request( 'RT-201-DETAIL' ) );
		$created          = $created_response->get_data();
		self::assertIsArray( $created );

		$list_request = new WP_REST_Request( 'GET', '/tagcore/v1/batches' );
		$list_request->set_param( 'per_page', 10 );
		$list_response = rest_do_request( $list_request );
		$list          = $list_response->get_data();

		self::assertSame( 200, $list_response->get_status() );
		self::assertIsArray( $list );
		self::assertCount( 1, $list['items'] );
		self::assertSame( 'RT-201-DETAIL', $list['items'][0]['batch_code'] );
		self::assertArrayNotHasKey( 'notes', $list['items'][0] );
		self::assertNull( $list['next_cursor'] );

		$detail_response = rest_do_request(
			new WP_REST_Request( 'GET', '/tagcore/v1/batches/' . $created['batch_id'] )
		);
		$detail          = $detail_response->get_data();

		self::assertSame( 200, $detail_response->get_status() );
		self::assertIsArray( $detail );
		self::assertSame( 'Initial production run.', $detail['notes'] );
	}

	/**
	 * Collection pagination must remain bounded and stable by descending Batch ID.
	 */
	public function test_list_uses_bounded_cursor_pagination(): void {
		$first  = rest_do_request( $this->create_request( 'RT-201-PAGE-1' ) )->get_data();
		$second = rest_do_request( $this->create_request( 'RT-201-PAGE-2' ) )->get_data();
		$third  = rest_do_request( $this->create_request( 'RT-201-PAGE-3' ) )->get_data();

		self::assertIsArray( $first );
		self::assertIsArray( $second );
		self::assertIsArray( $third );

		$request = new WP_REST_Request( 'GET', '/tagcore/v1/batches' );
		$request->set_param( 'per_page', 2 );
		$page_one_response = rest_do_request( $request );
		$page_one          = $page_one_response->get_data();

		self::assertSame( 200, $page_one_response->get_status() );
		self::assertIsArray( $page_one );
		self::assertSame(
			array( 'RT-201-PAGE-3', 'RT-201-PAGE-2' ),
			array_column( $page_one['items'], 'batch_code' )
		);
		self::assertSame( $second['batch_id'], $page_one['next_cursor'] );

		$request = new WP_REST_Request( 'GET', '/tagcore/v1/batches' );
		$request->set_param( 'per_page', 2 );
		$request->set_param( 'cursor', $page_one['next_cursor'] );
		$page_two_response = rest_do_request( $request );
		$page_two          = $page_two_response->get_data();

		self::assertSame( 200, $page_two_response->get_status() );
		self::assertIsArray( $page_two );
		self::assertSame( array( 'RT-201-PAGE-1' ), array_column( $page_two['items'], 'batch_code' ) );
		self::assertNull( $page_two['next_cursor'] );
	}

	/**
	 * Invalid canonical values must not reach persistence.
	 */
	public function test_invalid_values_fail_without_writes(): void {
		global $wpdb;

		$request = $this->create_request( 'RT-201-INVALID' );
		$request->set_param( 'tag_type', 'classic' );
		$request->set_param( 'smart_network', 'apple_find_my' );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		self::assertSame( 400, $response->get_status() );
		self::assertIsArray( $data );
		self::assertSame( 'returntag_invalid_batch_request', $data['code'] );

		$tables = new TableNames( $wpdb->prefix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted isolated test table.
		self::assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$tables->batches()}" ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted isolated test table.
		self::assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$tables->events()}" ) );
	}

	/**
	 * Build one realistic create request with attempted state overrides.
	 *
	 * @param string $batch_code Unique Batch Code.
	 */
	private function create_request( string $batch_code ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/tagcore/v1/batches' );
		$request->set_body_params(
			array(
				'batch_code'         => $batch_code,
				'tag_type'           => 'smart_tag',
				'model_code'         => 'SMART-01',
				'smart_network'      => 'apple_find_my',
				'requested_quantity' => 2500,
				'manufacturer'       => 'Northstar Manufacturing',
				'sales_channel'      => 'direct',
				'notes'              => 'Initial production run.',
				'batch_status'       => 'released',
				'generated_quantity' => 2500,
				'activation_enabled' => true,
				'created_by'         => 999999,
			)
		);

		return $request;
	}

	/**
	 * Model the REST server's final response filter for internal dispatch tests.
	 *
	 * Rest_do_request() stops before serve_request() applies rest_post_dispatch.
	 *
	 * @param WP_REST_Response $response Internal dispatch response.
	 * @param WP_REST_Request  $request Original REST request.
	 */
	private function assert_no_store_after_serving(
		WP_REST_Response $response,
		WP_REST_Request $request
	): void {
		$filtered = apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request );

		self::assertInstanceOf( WP_REST_Response::class, $filtered );
		self::assertSame( 'no-store, private', $filtered->get_headers()['Cache-Control'] );
	}

	/**
	 * Apply the production Migration chain.
	 *
	 * @param wpdb $database Active test database.
	 */
	private function migrate( wpdb $database ): void {
		$runner = new MigrationRunner(
			( new MigrationRegistryFactory( $database ) )->create(),
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
		);

		self::assertSame( 8, $runner->migrate()->ending_version );
	}

	/**
	 * Remove only trusted ReturnTag tables from the isolated test database.
	 *
	 * @param wpdb $database Active test database.
	 */
	private function clear_schema( wpdb $database ): void {
		$names = new TableNames( $database->prefix );

		foreach ( array( $names->events(), $names->access_tokens(), $names->messages(), $names->conversations(), $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated test cleanup with trusted identifiers.
			$database->query( "DROP TABLE IF EXISTS {$table_name}" );
		}

		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}
}
