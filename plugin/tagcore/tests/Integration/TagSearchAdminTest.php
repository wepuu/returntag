<?php
/**
 * RT-209 read-only Tag administration integration coverage.
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

/**
 * Verifies permissions, privacy-minimized projections, filters, and pagination.
 */
final class TagSearchAdminTest extends WP_UnitTestCase {
	/**
	 * Authorized administrator fixture.
	 *
	 * @var int
	 */
	private int $administrator_id;

	/**
	 * Prepare current isolated Schema and fixtures.
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
		$this->insert_fixture( $wpdb );
	}

	/**
	 * Remove isolated data and capabilities.
	 */
	protected function tearDown(): void {
		global $wpdb;

		$role = get_role( 'administrator' );

		if ( null !== $role ) {
			$role->remove_cap( Capability::MANAGE_RETURNTAG );
			$role->remove_cap( Capability::MANAGE_BATCHES );
			$role->remove_cap( Capability::MANAGE_TAGS );
		}

		delete_option( CapabilityInstaller::OPTION_NAME );
		delete_option( FeatureFlag::GLOBAL_ACTIVATION->value );
		$this->clear_schema( $wpdb );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Exact Tag ID input is normalized and returns only approved fields.
	 */
	public function test_exact_tag_id_search_normalizes_and_minimizes_response(): void {
		$request = new WP_REST_Request( 'GET', '/tagcore/v1/tags' );
		$request->set_param( 'mode', 'tag_id' );
		$request->set_param( 'tag_id', ' 23-45 67 ' );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertIsArray( $data );
		self::assertCount( 1, $data['items'] );
		self::assertSame( '234567', $data['items'][0]['tag_id'] );
		self::assertSame(
			array(
				'tag_id',
				'batch_id',
				'batch_code',
				'batch_status',
				'batch_activation_enabled',
				'activation_availability',
				'tag_type',
				'model_code',
				'tag_status',
				'lost_mode',
				'activated_at',
				'created_at',
				'updated_at',
			),
			array_keys( $data['items'][0] )
		);
		self::assertSame( 'generated', $data['items'][0]['batch_status'] );
		self::assertFalse( $data['items'][0]['batch_activation_enabled'] );
		self::assertSame( 'awaiting_release', $data['items'][0]['activation_availability'] );
		self::assertFalse( $data['context']['global_activation_enabled'] );
		self::assertArrayNotHasKey( 'owner_id', $data['items'][0] );
		self::assertArrayNotHasKey( 'item_name', $data['items'][0] );
		self::assertArrayNotHasKey( 'lost_message', $data['items'][0] );
		$this->assert_no_store_after_serving( $response, $request );
	}

	/**
	 * Search results preserve Tag state while explaining Batch activation controls.
	 */
	public function test_search_explains_batch_and_activation_state(): void {
		update_option( FeatureFlag::GLOBAL_ACTIVATION->value, '1', false );

		$released = $this->search_tag( '23456A' );
		self::assertSame( 'unregistered', $released['items'][0]['tag_status'] );
		self::assertSame( 'released', $released['items'][0]['batch_status'] );
		self::assertSame( 'eligible', $released['items'][0]['activation_availability'] );
		self::assertTrue( $released['context']['global_activation_enabled'] );

		$suspended = $this->search_tag( '23456B' );
		self::assertSame( 'unregistered', $suspended['items'][0]['tag_status'] );
		self::assertSame( 'suspended', $suspended['items'][0]['batch_status'] );
		self::assertSame(
			'blocked_batch_suspended',
			$suspended['items'][0]['activation_availability']
		);

		$voided = $this->search_tag( '23456C' );
		self::assertSame( 'unregistered', $voided['items'][0]['tag_status'] );
		self::assertSame( 'voided', $voided['items'][0]['batch_status'] );
		self::assertSame(
			'blocked_batch_voided',
			$voided['items'][0]['activation_availability']
		);

		$active = $this->search_tag( '234568' );
		self::assertSame( 'active', $active['items'][0]['tag_status'] );
		self::assertSame(
			'existing_activation_retained',
			$active['items'][0]['activation_availability']
		);

		update_option( FeatureFlag::GLOBAL_ACTIVATION->value, '0', false );
		$paused = $this->search_tag( '23456A' );
		self::assertSame( 'paused_globally', $paused['items'][0]['activation_availability'] );
		self::assertFalse( $paused['context']['global_activation_enabled'] );
	}

	/**
	 * Batch mode supports stable keyset pagination and status filtering.
	 */
	public function test_batch_search_pages_without_duplicates_and_filters_status(): void {
		$first = new WP_REST_Request( 'GET', '/tagcore/v1/tags' );
		$first->set_param( 'mode', 'batch' );
		$first->set_param( 'batch_code', 'RT-209-SEARCH' );
		$first->set_param( 'per_page', '1' );
		$first_response = rest_do_request( $first );
		$first_data     = $first_response->get_data();

		self::assertSame( 200, $first_response->get_status() );
		self::assertIsArray( $first_data );
		self::assertCount( 1, $first_data['items'] );
		self::assertIsString( $first_data['next_cursor'] );

		$second = new WP_REST_Request( 'GET', '/tagcore/v1/tags' );
		$second->set_param( 'mode', 'batch' );
		$second->set_param( 'batch_code', 'RT-209-SEARCH' );
		$second->set_param( 'per_page', '1' );
		$second->set_param( 'cursor', $first_data['next_cursor'] );
		$second_data = rest_do_request( $second )->get_data();

		self::assertIsArray( $second_data );
		self::assertNotSame( $first_data['items'][0]['tag_id'], $second_data['items'][0]['tag_id'] );

		$active = new WP_REST_Request( 'GET', '/tagcore/v1/tags' );
		$active->set_param( 'mode', 'batch' );
		$active->set_param( 'batch_code', 'RT-209-SEARCH' );
		$active->set_param( 'tag_status', 'active' );
		$active_data = rest_do_request( $active )->get_data();

		self::assertIsArray( $active_data );
		self::assertCount( 1, $active_data['items'] );
		self::assertSame( 'active', $active_data['items'][0]['tag_status'] );
	}

	/**
	 * Cursors cannot cross exact Batch filters.
	 */
	public function test_cursor_is_bound_to_normalized_filters(): void {
		$first = new WP_REST_Request( 'GET', '/tagcore/v1/tags' );
		$first->set_param( 'mode', 'batch' );
		$first->set_param( 'batch_code', 'RT-209-SEARCH' );
		$first->set_param( 'per_page', '1' );
		$data = rest_do_request( $first )->get_data();

		self::assertIsArray( $data );
		self::assertIsString( $data['next_cursor'] );

		$changed = new WP_REST_Request( 'GET', '/tagcore/v1/tags' );
		$changed->set_param( 'mode', 'batch' );
		$changed->set_param( 'batch_code', 'RT-209-OTHER' );
		$changed->set_param( 'cursor', $data['next_cursor'] );

		self::assertSame( 400, rest_do_request( $changed )->get_status() );
	}

	/**
	 * Search fails closed for missing permission or stale Schema.
	 */
	public function test_route_requires_capability_and_current_schema(): void {
		$request = new WP_REST_Request( 'GET', '/tagcore/v1/tags' );
		$request->set_param( 'mode', 'tag_id' );
		$request->set_param( 'tag_id', '234567' );

		wp_set_current_user( 0 );
		self::assertSame( 403, rest_do_request( $request )->get_status() );

		wp_set_current_user( $this->administrator_id );
		update_option( WordPressSchemaVersionStore::OPTION_NAME, 7, false );
		self::assertSame( 503, rest_do_request( $request )->get_status() );
	}

	/**
	 * Insert non-real privacy boundary fixtures.
	 *
	 * @param wpdb $database Active test database.
	 */
	private function insert_fixture( wpdb $database ): void {
		$tables = new TableNames( $database->prefix );
		$time   = '2026-07-29 08:00:00';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated typed fixture insert.
		self::assertSame(
			1,
			$database->insert(
				$tables->batches(),
				array(
					'batch_code'         => 'RT-209-SEARCH',
					'tag_type'           => 'classic_tag',
					'model_code'         => 'CLASSIC-01',
					'smart_network'      => 'none',
					'requested_quantity' => 3,
					'generated_quantity' => 3,
					'batch_status'       => 'generated',
					'activation_enabled' => 0,
					'created_by'         => $this->administrator_id,
					'created_at'         => $time,
					'updated_at'         => $time,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%s' )
			)
		);
		$batch_id = (int) $database->insert_id;

		foreach (
			array(
				array( '234567', 'unregistered', 0, null ),
				array( '234568', 'active', 1, $time ),
				array( '234569', 'suspended', 0, $time ),
			) as $tag
		) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated typed fixture insert.
			self::assertSame(
				1,
				$database->insert(
					$tables->tags(),
					array(
						'tag_id'       => $tag[0],
						'batch_id'     => $batch_id,
						'owner_id'     => 'active' === $tag[1] ? $this->administrator_id : null,
						'tag_type'     => 'classic_tag',
						'model_code'   => 'CLASSIC-01',
						'item_name'    => 'PRIVATE FIXTURE',
						'public_label' => 'Public fixture',
						'tag_status'   => $tag[1],
						'lost_mode'    => $tag[2],
						'lost_message' => 'PRIVATE MESSAGE FIXTURE',
						'activated_at' => $tag[3],
						'created_at'   => $time,
						'updated_at'   => $time,
					),
					array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
				)
			);
		}

		foreach (
			array(
				array( 'RT-209-RELEASED', 'released', 1, '23456A' ),
				array( 'RT-209-SUSPENDED', 'suspended', 0, '23456B' ),
				array( 'RT-209-VOIDED', 'voided', 0, '23456C' ),
			) as $fixture
		) {
			$this->insert_availability_fixture(
				$database,
				$tables,
				$time,
				$fixture[0],
				$fixture[1],
				(bool) $fixture[2],
				$fixture[3]
			);
		}
	}

	/**
	 * Insert one Batch and unregistered Tag for availability coverage.
	 *
	 * @param wpdb       $database Active test database.
	 * @param TableNames $tables Trusted table names.
	 * @param string     $time UTC fixture time.
	 * @param string     $batch_code Batch Code.
	 * @param string     $batch_status Canonical Batch status.
	 * @param bool       $activation_enabled Batch activation control.
	 * @param string     $tag_id Canonical Tag ID.
	 */
	private function insert_availability_fixture(
		wpdb $database,
		TableNames $tables,
		string $time,
		string $batch_code,
		string $batch_status,
		bool $activation_enabled,
		string $tag_id
	): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated typed fixture insert.
		self::assertSame(
			1,
			$database->insert(
				$tables->batches(),
				array(
					'batch_code'         => $batch_code,
					'tag_type'           => 'classic_tag',
					'model_code'         => 'CLASSIC-01',
					'smart_network'      => 'none',
					'requested_quantity' => 1,
					'generated_quantity' => 1,
					'batch_status'       => $batch_status,
					'activation_enabled' => $activation_enabled ? 1 : 0,
					'created_by'         => $this->administrator_id,
					'created_at'         => $time,
					'updated_at'         => $time,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%s' )
			)
		);
		$batch_id = (int) $database->insert_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated typed fixture insert.
		self::assertSame(
			1,
			$database->insert(
				$tables->tags(),
				array(
					'tag_id'     => $tag_id,
					'batch_id'   => $batch_id,
					'tag_type'   => 'classic_tag',
					'model_code' => 'CLASSIC-01',
					'tag_status' => 'unregistered',
					'lost_mode'  => 0,
					'created_at' => $time,
					'updated_at' => $time,
				),
				array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
			)
		);
	}

	/**
	 * Execute one authorized exact Tag search.
	 *
	 * @param string $tag_id Canonical Tag ID.
	 * @return array<string, mixed>
	 */
	private function search_tag( string $tag_id ): array {
		$request = new WP_REST_Request( 'GET', '/tagcore/v1/tags' );
		$request->set_param( 'mode', 'tag_id' );
		$request->set_param( 'tag_id', $tag_id );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertIsArray( $data );
		self::assertCount( 1, $data['items'] );

		return $data;
	}

	/**
	 * Model final REST response filtering.
	 *
	 * @param WP_REST_Response $response REST response.
	 * @param WP_REST_Request  $request REST request.
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
