<?php
/**
 * RT-201 Batch administration integration coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Admin\BatchCsvDownload;
use ReturnTag\TagCore\Admin\Capability;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerBatchGenerationScheduler;
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
		$this->clear_generation_actions();
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
		$this->clear_generation_actions();
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
				new WP_REST_Request( 'GET', '/tagcore/v1/batches/1/generation' ),
				new WP_REST_Request( 'GET', '/tagcore/v1/batches/1/tags' ),
				new WP_REST_Request( 'GET', '/tagcore/v1/batches/1/exports' ),
				new WP_REST_Request( 'POST', '/tagcore/v1/batches' ),
				new WP_REST_Request( 'POST', '/tagcore/v1/batches/1/generation' ),
				new WP_REST_Request( 'POST', '/tagcore/v1/batches/1/exports' ),
			) as $request
		) {
			self::assertSame( 403, rest_do_request( $request )->get_status() );
		}
	}

	/**
	 * Draft progress is a safe aggregate and can start only while disabled.
	 */
	public function test_generation_progress_returns_draft_aggregate(): void {
		$created = rest_do_request( $this->create_request( 'RT-205-DRAFT' ) )->get_data();
		self::assertIsArray( $created );

		$request  = new WP_REST_Request(
			'GET',
			'/tagcore/v1/batches/' . $created['batch_id'] . '/generation'
		);
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertIsArray( $data );
		self::assertSame(
			array(
				'batch_id',
				'batch_status',
				'requested_quantity',
				'generated_quantity',
				'remaining_quantity',
				'failed_quantity',
				'progress_percent',
				'started_at',
				'completed_at',
				'last_progress_at',
				'queue_state',
				'can_start',
				'can_retry',
				'poll_after_ms',
			),
			array_keys( $data )
		);
		self::assertSame( 'draft', $data['batch_status'] );
		self::assertSame( 2500, $data['requested_quantity'] );
		self::assertSame( 0, $data['generated_quantity'] );
		self::assertSame( 2500, $data['remaining_quantity'] );
		self::assertSame( 0, $data['failed_quantity'] );
		self::assertSame( 0, $data['progress_percent'] );
		self::assertNull( $data['started_at'] );
		self::assertNull( $data['completed_at'] );
		self::assertSame( 'idle', $data['queue_state'] );
		self::assertTrue( $data['can_start'] );
		self::assertFalse( $data['can_retry'] );
		self::assertSame( 0, $data['poll_after_ms'] );
		$this->assert_no_store_after_serving( $response, $request );
	}

	/**
	 * Starting generation is idempotent and queues only one checkpoint action.
	 */
	public function test_generation_route_starts_and_resumes_without_duplicate_event_or_action(): void {
		global $wpdb;

		$created = rest_do_request( $this->create_request( 'RT-204-START' ) )->get_data();
		self::assertIsArray( $created );

		$request = new WP_REST_Request(
			'POST',
			'/tagcore/v1/batches/' . $created['batch_id'] . '/generation'
		);
		$request->set_body_params(
			array(
				'checkpoint'         => 999999,
				'retry_attempt'      => 999999,
				'generated_quantity' => 999999,
				'batch_status'       => 'released',
				'activation_enabled' => true,
				'tag_id'             => 'N7R2W8',
			)
		);
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		self::assertSame( 202, $response->get_status() );
		self::assertIsArray( $data );
		self::assertSame( 'generating', $data['batch_status'] );
		self::assertSame( 0, $data['generated_quantity'] );
		self::assertSame( 'queued', $data['queue_status'] );
		self::assertSame(
			array( 'batch_id', 'batch_status', 'generated_quantity', 'queue_status' ),
			array_keys( $data )
		);
		self::assertTrue(
			as_has_scheduled_action(
				ActionSchedulerBatchGenerationScheduler::HOOK,
				array(
					'batch_id'      => $created['batch_id'],
					'checkpoint'    => 0,
					'retry_attempt' => 0,
				),
				ActionSchedulerBatchGenerationScheduler::GROUP
			)
		);

		$resume      = rest_do_request( $request );
		$resume_data = $resume->get_data();

		self::assertSame( 202, $resume->get_status() );
		self::assertIsArray( $resume_data );
		self::assertSame( 'already_scheduled', $resume_data['queue_status'] );

		$progress_request  = new WP_REST_Request(
			'GET',
			'/tagcore/v1/batches/' . $created['batch_id'] . '/generation'
		);
		$progress_response = rest_do_request( $progress_request );
		$progress          = $progress_response->get_data();

		self::assertSame( 200, $progress_response->get_status() );
		self::assertIsArray( $progress );
		self::assertSame( 'generating', $progress['batch_status'] );
		self::assertSame( 'scheduled', $progress['queue_state'] );
		self::assertNotNull( $progress['started_at'] );
		self::assertNull( $progress['completed_at'] );
		self::assertSame( 3000, $progress['poll_after_ms'] );
		self::assertFalse( $progress['can_retry'] );

		$tables      = new TableNames( $wpdb->prefix );
		$event_query = $wpdb->prepare(
			'SELECT COUNT(*) FROM %i WHERE event_type = %s AND target_id = %s',
			$tables->events(),
			'batch_generation_started',
			(string) $created['batch_id']
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Verifying one audit Event in an isolated test table.
		self::assertSame( '1', $wpdb->get_var( $event_query ) );
	}

	/**
	 * A generating Batch without queued work exposes an idempotent recovery action.
	 */
	public function test_generation_progress_needs_attention_without_scheduled_work(): void {
		$created = rest_do_request( $this->create_request( 'RT-205-RETRY' ) )->get_data();
		self::assertIsArray( $created );

		$start = new WP_REST_Request(
			'POST',
			'/tagcore/v1/batches/' . $created['batch_id'] . '/generation'
		);
		self::assertSame( 202, rest_do_request( $start )->get_status() );

		as_unschedule_all_actions(
			ActionSchedulerBatchGenerationScheduler::HOOK,
			array(
				'batch_id'      => $created['batch_id'],
				'checkpoint'    => 0,
				'retry_attempt' => 0,
			),
			ActionSchedulerBatchGenerationScheduler::GROUP
		);

		$progress = rest_do_request(
			new WP_REST_Request(
				'GET',
				'/tagcore/v1/batches/' . $created['batch_id'] . '/generation'
			)
		)->get_data();

		self::assertIsArray( $progress );
		self::assertSame( 'needs_attention', $progress['queue_state'] );
		self::assertTrue( $progress['can_retry'] );
		self::assertSame( 0, $progress['poll_after_ms'] );
	}

	/**
	 * Completed generation returns audited terminal progress without queue details.
	 */
	public function test_generation_progress_returns_completed_state(): void {
		$request = $this->create_request( 'RT-205-COMPLETE' );
		$request->set_param( 'requested_quantity', 1 );
		$created = rest_do_request( $request )->get_data();
		self::assertIsArray( $created );

		$start = new WP_REST_Request(
			'POST',
			'/tagcore/v1/batches/' . $created['batch_id'] . '/generation'
		);
		self::assertSame( 202, rest_do_request( $start )->get_status() );

		as_unschedule_all_actions(
			ActionSchedulerBatchGenerationScheduler::HOOK,
			array(
				'batch_id'      => $created['batch_id'],
				'checkpoint'    => 0,
				'retry_attempt' => 0,
			),
			ActionSchedulerBatchGenerationScheduler::GROUP
		);
		do_action(
			ActionSchedulerBatchGenerationScheduler::HOOK,
			$created['batch_id'],
			0,
			0
		);

		$response = rest_do_request(
			new WP_REST_Request(
				'GET',
				'/tagcore/v1/batches/' . $created['batch_id'] . '/generation'
			)
		);
		$progress = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertIsArray( $progress );
		self::assertSame( 'generated', $progress['batch_status'] );
		self::assertSame( 1, $progress['generated_quantity'] );
		self::assertSame( 0, $progress['remaining_quantity'] );
		self::assertSame( 100, $progress['progress_percent'] );
		self::assertSame( 'complete', $progress['queue_state'] );
		self::assertNotNull( $progress['started_at'] );
		self::assertNotNull( $progress['completed_at'] );
		self::assertFalse( $progress['can_start'] );
		self::assertFalse( $progress['can_retry'] );
		self::assertSame( 0, $progress['poll_after_ms'] );
	}

	/**
	 * Generated inventory is minimal, deterministic, and complete across pages.
	 */
	public function test_generated_tag_inventory_uses_stable_opaque_pagination(): void {
		$created = $this->complete_generation( 'RT-206-INVENTORY', 101 );
		$route   = '/tagcore/v1/batches/' . $created['batch_id'] . '/tags';

		$page_one_request  = new WP_REST_Request( 'GET', $route );
		$page_one_response = rest_do_request( $page_one_request );
		$page_one          = $page_one_response->get_data();

		self::assertSame( 200, $page_one_response->get_status() );
		self::assertIsArray( $page_one );
		self::assertCount( 50, $page_one['items'] );
		self::assertIsString( $page_one['next_cursor'] );
		self::assertNotSame( $page_one['items'][49]['tag_id'], $page_one['next_cursor'] );
		$this->assert_no_store_after_serving( $page_one_response, $page_one_request );

		foreach ( $page_one['items'] as $item ) {
			self::assertSame( array( 'tag_id', 'tag_status', 'created_at' ), array_keys( $item ) );
			self::assertSame( 'unregistered', $item['tag_status'] );
			self::assertMatchesRegularExpression( '/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6}$/D', $item['tag_id'] );
			self::assertStringEndsWith( '+00:00', $item['created_at'] );
		}

		$page_two_request = new WP_REST_Request( 'GET', $route );
		$page_two_request->set_param( 'per_page', 50 );
		$page_two_request->set_param( 'cursor', $page_one['next_cursor'] );
		$page_two_response = rest_do_request( $page_two_request );
		$page_two          = $page_two_response->get_data();

		self::assertSame( 200, $page_two_response->get_status() );
		self::assertIsArray( $page_two );
		self::assertCount( 50, $page_two['items'] );
		self::assertIsString( $page_two['next_cursor'] );

		$page_three_request = new WP_REST_Request( 'GET', $route );
		$page_three_request->set_param( 'per_page', 50 );
		$page_three_request->set_param( 'cursor', $page_two['next_cursor'] );
		$page_three = rest_do_request( $page_three_request )->get_data();

		self::assertIsArray( $page_three );
		self::assertCount( 1, $page_three['items'] );
		self::assertNull( $page_three['next_cursor'] );

		$tag_ids = array_merge(
			array_column( $page_one['items'], 'tag_id' ),
			array_column( $page_two['items'], 'tag_id' ),
			array_column( $page_three['items'], 'tag_id' )
		);
		$sorted  = $tag_ids;
		sort( $sorted, SORT_STRING );

		self::assertSame( $sorted, $tag_ids );
		self::assertCount( 101, array_unique( $tag_ids ) );

		$max_request = new WP_REST_Request( 'GET', $route );
		$max_request->set_param( 'per_page', 100 );
		$max_page = rest_do_request( $max_request )->get_data();

		self::assertIsArray( $max_page );
		self::assertCount( 100, $max_page['items'] );
		self::assertIsString( $max_page['next_cursor'] );
	}

	/**
	 * First export streams exact CSV, records audit, and changes the Batch state.
	 */
	public function test_first_csv_export_is_deterministic_audited_and_downloadable(): void {
		global $wpdb;

		$created  = $this->complete_generation( 'RT-207-FIRST', 3 );
		$route    = '/tagcore/v1/batches/' . $created['batch_id'] . '/exports';
		$request  = new WP_REST_Request( 'POST', $route );
		$response = rest_do_request( $request );
		$headers  = $response->get_headers();
		$csv      = $this->serve_csv_response( $response, $request );
		$lines    = explode( "\r\n", rtrim( $csv, "\r\n" ) );

		self::assertSame( 200, $response->get_status() );
		self::assertSame( 'text/csv; charset=UTF-8', $headers['Content-Type'] );
		self::assertSame( 'no-store, private', $headers['Cache-Control'] );
		self::assertSame( '1', $headers['X-ReturnTag-Export-Version'] );
		self::assertSame( '3', $headers['X-ReturnTag-Row-Count'] );
		self::assertSame( hash( 'sha256', $csv ), $headers['X-ReturnTag-SHA256'] );
		self::assertSame( 'exported', $headers['X-ReturnTag-Batch-Status'] );
		self::assertStringContainsString( 'tagcore-RT-207-FIRST-v1.csv', $headers['Content-Disposition'] );
		self::assertCount( 4, $lines );
		self::assertSame(
			'sequence_no,batch_code,tag_id,tag_type,model_code,smart_network,qr_url',
			$lines[0]
		);

		$tag_ids = array();

		foreach ( array_slice( $lines, 1 ) as $index => $line ) {
			$fields = str_getcsv( $line );
			self::assertCount( 7, $fields );
			self::assertSame( (string) ( $index + 1 ), $fields[0] );
			self::assertSame( 'RT-207-FIRST', $fields[1] );
			self::assertSame( 'smart_tag', $fields[3] );
			self::assertSame( 'SMART-01', $fields[4] );
			self::assertSame( 'apple_find_my', $fields[5] );
			self::assertSame( home_url( '/t/' . $fields[2] ), $fields[6] );
			$tag_ids[] = $fields[2];
		}

		$sorted = $tag_ids;
		sort( $sorted, SORT_STRING );
		self::assertSame( $sorted, $tag_ids );

		$tables      = new TableNames( $wpdb->prefix );
		$batch_query = $wpdb->prepare(
			'SELECT batch_status, activation_enabled FROM %i WHERE batch_id = %d',
			$tables->batches(),
			$created['batch_id']
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Isolated export state verification.
		$batch = $wpdb->get_row( $batch_query, ARRAY_A );
		self::assertIsArray( $batch );
		self::assertSame( 'exported', $batch['batch_status'] );
		self::assertSame( '0', $batch['activation_enabled'] );

		$export_query = $wpdb->prepare(
			'SELECT export_version, row_count, file_format, file_checksum, created_by FROM %i WHERE batch_id = %d',
			$tables->batch_exports(),
			$created['batch_id']
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Isolated export audit verification.
		$export = $wpdb->get_row( $export_query, ARRAY_A );
		self::assertIsArray( $export );
		self::assertSame( '1', $export['export_version'] );
		self::assertSame( '3', $export['row_count'] );
		self::assertSame( 'csv', $export['file_format'] );
		self::assertSame( hash( 'sha256', $csv ), $export['file_checksum'] );
		self::assertSame( (string) $this->administrator_id, $export['created_by'] );

		$event_query = $wpdb->prepare(
			'SELECT COUNT(*) FROM %i WHERE event_type = %s AND target_id = %s',
			$tables->events(),
			'batch_exported',
			(string) $created['batch_id']
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Isolated audit Event verification.
		self::assertSame( '1', $wpdb->get_var( $event_query ) );
	}

	/**
	 * Re-export creates a new version with identical bytes and no new Tag IDs.
	 */
	public function test_csv_reexport_preserves_exact_bytes_and_lists_history(): void {
		global $wpdb;

		$created = $this->complete_generation( 'RT-207-REEXPORT', 2 );
		$route   = '/tagcore/v1/batches/' . $created['batch_id'] . '/exports';
		$before  = $this->count_batch_tags( $wpdb, (int) $created['batch_id'] );

		$first_request  = new WP_REST_Request( 'POST', $route );
		$first_response = rest_do_request( $first_request );
		$first_csv      = $this->serve_csv_response( $first_response, $first_request );

		$second_request  = new WP_REST_Request( 'POST', $route );
		$second_response = rest_do_request( $second_request );
		$second_csv      = $this->serve_csv_response( $second_response, $second_request );

		self::assertSame( $first_csv, $second_csv );
		self::assertSame( '1', $first_response->get_headers()['X-ReturnTag-Export-Version'] );
		self::assertSame( '2', $second_response->get_headers()['X-ReturnTag-Export-Version'] );
		self::assertSame(
			$first_response->get_headers()['X-ReturnTag-SHA256'],
			$second_response->get_headers()['X-ReturnTag-SHA256']
		);
		self::assertSame( $before, $this->count_batch_tags( $wpdb, (int) $created['batch_id'] ) );

		$history_request  = new WP_REST_Request( 'GET', $route );
		$history_response = rest_do_request( $history_request );
		$history          = $history_response->get_data();

		self::assertSame( 200, $history_response->get_status() );
		self::assertIsArray( $history );
		self::assertCount( 2, $history['items'] );
		self::assertSame( 2, $history['items'][0]['export_version'] );
		self::assertSame( 1, $history['items'][1]['export_version'] );
		self::assertSame( $this->administrator_id, $history['items'][0]['created_by'] );
		self::assertNotSame( '', $history['items'][0]['created_by_name'] );
		self::assertNull( $history['next_cursor'] );
		$this->assert_no_store_after_serving( $history_response, $history_request );
	}

	/**
	 * Incomplete and incident-state Batches fail without partial audit writes.
	 */
	public function test_csv_export_fails_closed_for_incomplete_and_suspended_batches(): void {
		global $wpdb;

		$draft = rest_do_request( $this->create_request( 'RT-207-DRAFT' ) )->get_data();
		self::assertIsArray( $draft );
		$draft_response = rest_do_request(
			new WP_REST_Request(
				'POST',
				'/tagcore/v1/batches/' . $draft['batch_id'] . '/exports'
			)
		);
		self::assertSame( 409, $draft_response->get_status() );

		$generated = $this->complete_generation( 'RT-207-SUSPENDED', 1 );
		$tables    = new TableNames( $wpdb->prefix );
		$update    = $wpdb->prepare(
			'UPDATE %i SET batch_status = %s WHERE batch_id = %d',
			$tables->batches(),
			'suspended',
			$generated['batch_id']
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Isolated incident-state fixture.
		self::assertSame( 1, $wpdb->query( $update ) );

		$suspended_response = rest_do_request(
			new WP_REST_Request(
				'POST',
				'/tagcore/v1/batches/' . $generated['batch_id'] . '/exports'
			)
		);
		self::assertSame( 409, $suspended_response->get_status() );

		$count_query = $wpdb->prepare(
			'SELECT COUNT(*) FROM %i WHERE batch_id IN (%d, %d)',
			$tables->batch_exports(),
			$draft['batch_id'],
			$generated['batch_id']
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Isolated absence verification.
		self::assertSame( '0', $wpdb->get_var( $count_query ) );
	}

	/**
	 * Unknown persisted Tag states fail behind the fixed privacy-safe response.
	 */
	public function test_inventory_rejects_unknown_stored_tag_status(): void {
		global $wpdb;

		$created = $this->complete_generation( 'RT-206-UNKNOWN', 1 );
		$tables  = new TableNames( $wpdb->prefix );
		$query   = $wpdb->prepare(
			'UPDATE %i SET tag_status = %s WHERE batch_id = %d',
			$tables->tags(),
			'future_status',
			$created['batch_id']
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Deliberately corrupting one isolated fixture to verify fail-closed mapping.
		self::assertSame( 1, $wpdb->query( $query ) );

		$response = rest_do_request(
			new WP_REST_Request(
				'GET',
				'/tagcore/v1/batches/' . $created['batch_id'] . '/tags'
			)
		);
		$data     = $response->get_data();

		self::assertSame( 500, $response->get_status() );
		self::assertIsArray( $data );
		self::assertSame( 'returntag_batch_operation_failed', $data['code'] );
		self::assertStringNotContainsString( 'future_status', wp_json_encode( $data ) );
	}

	/**
	 * A 2,500-row inventory remains bounded and produces accepted EXPLAIN plans.
	 */
	public function test_inventory_query_is_bounded_for_2500_tag_batch(): void {
		global $wpdb;

		$created = rest_do_request( $this->create_request( 'RT-206-EXPLAIN' ) )->get_data();
		self::assertIsArray( $created );
		$this->insert_inventory_fixture( $wpdb, (int) $created['batch_id'], 2500 );

		$tables = new TableNames( $wpdb->prefix );
		$update = $wpdb->prepare(
			'UPDATE %i SET generated_quantity = %d, batch_status = %s WHERE batch_id = %d',
			$tables->batches(),
			2500,
			'generated',
			$created['batch_id']
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Isolated 2,500-row performance fixture.
		self::assertSame( 1, $wpdb->query( $update ) );

		$first_plan_query = $wpdb->prepare(
			'EXPLAIN SELECT tag_id, tag_status, created_at FROM %i WHERE batch_id = %d ORDER BY tag_id ASC LIMIT %d',
			$tables->tags(),
			$created['batch_id'],
			51
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared EXPLAIN against an isolated synthetic fixture.
		$first_plan = $wpdb->get_row( $first_plan_query, ARRAY_A );
		self::assertIsArray( $first_plan );
		self::assertNotSame( '', (string) $first_plan['possible_keys'] );

		$route    = '/tagcore/v1/batches/' . $created['batch_id'] . '/tags';
		$request  = new WP_REST_Request( 'GET', $route );
		$response = rest_do_request( $request );
		$page     = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertIsArray( $page );
		self::assertCount( 50, $page['items'] );
		self::assertIsString( $page['next_cursor'] );

		$last_tag_id     = $page['items'][49]['tag_id'];
		$next_plan_query = $wpdb->prepare(
			'EXPLAIN SELECT tag_id, tag_status, created_at FROM %i WHERE batch_id = %d AND tag_id > %s ORDER BY tag_id ASC LIMIT %d',
			$tables->tags(),
			$created['batch_id'],
			$last_tag_id,
			51
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared continuation EXPLAIN against an isolated synthetic fixture.
		$next_plan = $wpdb->get_row( $next_plan_query, ARRAY_A );
		self::assertIsArray( $next_plan );
		self::assertNotSame( '', (string) $next_plan['possible_keys'] );

		$next_request = new WP_REST_Request( 'GET', $route );
		$next_request->set_param( 'cursor', $page['next_cursor'] );
		$next_page = rest_do_request( $next_request )->get_data();
		self::assertIsArray( $next_page );
		self::assertCount( 50, $next_page['items'] );
		self::assertGreaterThan( $last_tag_id, $next_page['items'][0]['tag_id'] );
	}

	/**
	 * Incomplete, missing, malformed, and oversized inventory reads fail safely.
	 */
	public function test_tag_inventory_validates_batch_state_and_query_parameters(): void {
		$draft = rest_do_request( $this->create_request( 'RT-206-DRAFT' ) )->get_data();
		self::assertIsArray( $draft );

		$route = '/tagcore/v1/batches/' . $draft['batch_id'] . '/tags';

		$unavailable      = rest_do_request( new WP_REST_Request( 'GET', $route ) );
		$unavailable_data = $unavailable->get_data();
		self::assertSame( 409, $unavailable->get_status() );
		self::assertIsArray( $unavailable_data );
		self::assertSame( 'returntag_batch_tag_inventory_unavailable', $unavailable_data['code'] );

		$missing = rest_do_request(
			new WP_REST_Request( 'GET', '/tagcore/v1/batches/999999999/tags' )
		);
		self::assertSame( 404, $missing->get_status() );

		$invalid_cursor = new WP_REST_Request( 'GET', $route );
		$invalid_cursor->set_param( 'cursor', 'not+a+cursor' );
		self::assertSame( 400, rest_do_request( $invalid_cursor )->get_status() );

		$oversized = new WP_REST_Request( 'GET', $route );
		$oversized->set_param( 'per_page', 101 );
		self::assertSame( 400, rest_do_request( $oversized )->get_status() );
	}

	/**
	 * Queue matching must not confuse work for another Batch.
	 */
	public function test_generation_queue_monitor_is_scoped_to_batch_id(): void {
		$first  = rest_do_request( $this->create_request( 'RT-205-SCOPE-1' ) )->get_data();
		$second = rest_do_request( $this->create_request( 'RT-205-SCOPE-2' ) )->get_data();
		self::assertIsArray( $first );
		self::assertIsArray( $second );

		foreach ( array( $first, $second ) as $batch ) {
			$request = new WP_REST_Request(
				'POST',
				'/tagcore/v1/batches/' . $batch['batch_id'] . '/generation'
			);
			self::assertSame( 202, rest_do_request( $request )->get_status() );
		}

		as_unschedule_all_actions(
			ActionSchedulerBatchGenerationScheduler::HOOK,
			array(
				'batch_id'      => $first['batch_id'],
				'checkpoint'    => 0,
				'retry_attempt' => 0,
			),
			ActionSchedulerBatchGenerationScheduler::GROUP
		);

		$first_progress  = rest_do_request(
			new WP_REST_Request(
				'GET',
				'/tagcore/v1/batches/' . $first['batch_id'] . '/generation'
			)
		)->get_data();
		$second_progress = rest_do_request(
			new WP_REST_Request(
				'GET',
				'/tagcore/v1/batches/' . $second['batch_id'] . '/generation'
			)
		)->get_data();

		self::assertIsArray( $first_progress );
		self::assertIsArray( $second_progress );
		self::assertSame( 'needs_attention', $first_progress['queue_state'] );
		self::assertSame( 'scheduled', $second_progress['queue_state'] );
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

		$inventory_request  = new WP_REST_Request( 'GET', '/tagcore/v1/batches/1/tags' );
		$inventory_response = rest_do_request( $inventory_request );
		self::assertSame( 503, $inventory_response->get_status() );
		$this->assert_no_store_after_serving( $inventory_response, $inventory_request );

		$export_request  = new WP_REST_Request( 'POST', '/tagcore/v1/batches/1/exports' );
		$export_response = rest_do_request( $export_request );
		self::assertSame( 503, $export_response->get_status() );
		$this->assert_no_store_after_serving( $export_response, $export_request );
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
	 * Create and synchronously finish one isolated Batch fixture.
	 *
	 * @param string $batch_code Unique Batch Code.
	 * @param int    $quantity Requested quantity.
	 * @return array<string, mixed>
	 */
	private function complete_generation( string $batch_code, int $quantity ): array {
		$request = $this->create_request( $batch_code );
		$request->set_param( 'requested_quantity', $quantity );
		$created = rest_do_request( $request )->get_data();
		self::assertIsArray( $created );

		$start = new WP_REST_Request(
			'POST',
			'/tagcore/v1/batches/' . $created['batch_id'] . '/generation'
		);
		self::assertSame( 202, rest_do_request( $start )->get_status() );

		for ( $checkpoint = 0; $checkpoint < $quantity; $checkpoint += 100 ) {
			as_unschedule_all_actions(
				ActionSchedulerBatchGenerationScheduler::HOOK,
				array(
					'batch_id'      => $created['batch_id'],
					'checkpoint'    => $checkpoint,
					'retry_attempt' => 0,
				),
				ActionSchedulerBatchGenerationScheduler::GROUP
			);
			do_action(
				ActionSchedulerBatchGenerationScheduler::HOOK,
				$created['batch_id'],
				$checkpoint,
				0
			);
		}

		return $created;
	}

	/**
	 * Insert one synthetic, non-PII inventory fixture in bounded SQL chunks.
	 *
	 * @param wpdb $database Active test database.
	 * @param int  $batch_id Batch identifier.
	 * @param int  $quantity Fixture size.
	 */
	private function insert_inventory_fixture(
		wpdb $database,
		int $batch_id,
		int $quantity
	): void {
		$tables = new TableNames( $database->prefix );
		$time   = '2026-07-27 09:00:00';

		for ( $offset = 0; $offset < $quantity; $offset += 500 ) {
			$values = array();
			$end    = min( $offset + 500, $quantity );

			for ( $index = $offset; $index < $end; ++$index ) {
				$values[] = $database->prepare(
					'(%s,%d,%s,%s,%s,%d,%s,%s)',
					$this->fixture_tag_id( $index ),
					$batch_id,
					'smart_tag',
					'SMART-01',
					'unregistered',
					0,
					$time,
					$time
				);
			}

			$query = sprintf(
				'INSERT INTO %s (tag_id,batch_id,tag_type,model_code,tag_status,lost_mode,created_at,updated_at) VALUES %s',
				$tables->tags(),
				implode( ',', $values )
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Synthetic values are prepared above and the table is a trusted isolated fixture.
			self::assertSame( count( $values ), $database->query( $query ) );
		}
	}

	/**
	 * Return a deterministic six-character synthetic Tag ID.
	 *
	 * @param int $index Zero-based fixture index.
	 */
	private function fixture_tag_id( int $index ): string {
		$alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
		$value    = '';

		for ( $position = 0; $position < 6; ++$position ) {
			$value = $alphabet[ $index % strlen( $alphabet ) ] . $value;
			$index = intdiv( $index, strlen( $alphabet ) );
		}

		return $value;
	}

	/**
	 * Run the REST binary serving filter and capture exact CSV bytes.
	 *
	 * @param WP_REST_Response $response Prepared response.
	 * @param WP_REST_Request  $request Original request.
	 */
	private function serve_csv_response(
		WP_REST_Response $response,
		WP_REST_Request $request
	): string {
		self::assertInstanceOf( BatchCsvDownload::class, $response->get_data() );
		ob_start();

		try {
			$served = apply_filters(
				'rest_pre_serve_request',
				false,
				$response,
				$request,
				rest_get_server()
			);
			$output = ob_get_contents();
		} finally {
			ob_end_clean();
		}

		self::assertTrue( $served );
		self::assertIsString( $output );

		return $output;
	}

	/**
	 * Count Tags assigned to one isolated Batch.
	 *
	 * @param wpdb $database Active test database.
	 * @param int  $batch_id Batch identifier.
	 */
	private function count_batch_tags( wpdb $database, int $batch_id ): int {
		$tables = new TableNames( $database->prefix );
		$query  = $database->prepare(
			'SELECT COUNT(*) FROM %i WHERE batch_id = %d',
			$tables->tags(),
			$batch_id
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Isolated fixture count.
		return (int) $database->get_var( $query );
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

	/**
	 * Cancel only pending RT-204 actions in the isolated test site.
	 */
	private function clear_generation_actions(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions(
				'',
				array(),
				ActionSchedulerBatchGenerationScheduler::GROUP
			);
		}
	}
}
