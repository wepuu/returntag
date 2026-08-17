<?php
/**
 * RT-326 operations console integration coverage.
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

/** Verifies exact anchors, permission boundaries, and safe projections. */
final class AdminOperationsConsoleTest extends WP_UnitTestCase {
	/**
	 * Authorized administrator fixture.
	 *
	 * @var int
	 */
	private int $administrator_id;

	/**
	 * Owner user fixture.
	 *
	 * @var int
	 */
	private int $owner_id;

	/**
	 * Unique Owner email fixture.
	 *
	 * @var string
	 */
	private string $owner_email;

	/**
	 * Finder Report fixture identifier.
	 *
	 * @var int
	 */
	private int $finder_report_id;

	/** Prepare a current Schema and privacy-boundary fixtures. */
	protected function setUp(): void {
		parent::setUp();

		global $wpdb;

		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->clear_schema( $wpdb );
		$this->migrate( $wpdb );
		$this->administrator_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->owner_email      = 'owner-rt326-' . $this->administrator_id . '@example.test';
		$owner                  = self::factory()->user->create(
			array(
				'user_login' => 'rt326-owner-' . $this->administrator_id,
				'user_email' => $this->owner_email,
			)
		);
		self::assertIsInt( $owner );
		$this->owner_id = $owner;
		wp_set_current_user( $this->administrator_id );
		( new CapabilityInstaller( RETURNTAG_TAGCORE_FILE ) )->install();
		rest_get_server();
		$this->insert_fixtures( $wpdb );
	}

	/** Remove isolated data, feature controls, and role grants. */
	protected function tearDown(): void {
		global $wpdb;

		$role = get_role( 'administrator' );
		if ( null !== $role ) {
			foreach (
				array(
					Capability::MANAGE_RETURNTAG,
					Capability::MANAGE_BATCHES,
					Capability::MANAGE_TAGS,
					Capability::MANAGE_TAG_LIFECYCLE,
					Capability::MANAGE_DISPUTES,
					Capability::VIEW_USERS,
					Capability::VIEW_AUDIT_LOGS,
				) as $capability
			) {
				$role->remove_cap( $capability );
			}
		}

		delete_option( CapabilityInstaller::OPTION_NAME );
		delete_option( FeatureFlag::ADMIN_SENSITIVE_PREVIEW->value );
		$this->clear_schema( $wpdb );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/** Exact Owner email stays in a POST body and returns a safe Tag projection. */
	public function test_owner_email_tag_search_is_exact_and_privacy_minimized(): void {
		$request  = $this->post(
			'/tagcore/v1/admin/tags/search',
			array(
				'mode'        => 'owner_email',
				'owner_email' => $this->owner_email,
			)
		);
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertSame( '/tagcore/v1/admin/tags/search', $request->get_route() );
		self::assertIsArray( $data );
		self::assertCount( 1, $data['items'] );
		self::assertSame( '234567', $data['items'][0]['tag_id'] );
		self::assertSame( $this->owner_id, $data['items'][0]['owner_id'] );
		self::assertArrayNotHasKey( 'item_name', $data['items'][0] );
		self::assertArrayNotHasKey( 'lost_message', $data['items'][0] );
		$json = wp_json_encode( $data );
		self::assertIsString( $json );
		self::assertStringNotContainsString( 'PRIVATE', $json );
		$this->assert_private_headers( $response, $request );
	}

	/** User support returns approved summaries without authentication material. */
	public function test_user_email_search_returns_support_projection_only(): void {
		$request  = $this->post(
			'/tagcore/v1/admin/users/search',
			array(
				'mode'  => 'email',
				'email' => $this->owner_email,
			)
		);
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertIsArray( $data );
		self::assertSame( $this->owner_id, $data['items'][0]['user_id'] );
		self::assertSame( 1, $data['items'][0]['tag_count'] );
		self::assertSame( 1, $data['items'][0]['finder_report_count'] );
		self::assertArrayNotHasKey( 'user_pass', $data['items'][0] );
		$json = wp_json_encode( $data );
		self::assertIsString( $json );
		self::assertStringNotContainsString( 'otp', strtolower( $json ) );
	}

	/** Finder metadata excludes message bytes and internal object references. */
	public function test_finder_report_search_and_detail_use_allowlisted_metadata(): void {
		$request  = $this->post(
			'/tagcore/v1/admin/finder-reports/search',
			array(
				'mode'   => 'tag_id',
				'tag_id' => '234567',
			)
		);
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertIsArray( $data );
		self::assertCount( 1, $data['items'] );
		self::assertArrayNotHasKey( 'has_message', $data['items'][0] );
		$json = wp_json_encode( $data );
		self::assertIsString( $json );
		self::assertStringNotContainsString( 'PRIVATE MESSAGE', $json );

		$detail = new WP_REST_Request( 'GET', '/tagcore/v1/admin/finder-reports/' . $this->finder_report_id );
		$detail->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$detail_data = rest_do_request( $detail )->get_data();
		self::assertIsArray( $detail_data );
		self::assertTrue( $detail_data['has_message'] );
		self::assertArrayNotHasKey( 'message_ciphertext', $detail_data );
		self::assertArrayNotHasKey( 'conversation_id', $detail_data );
	}

	/** Missing nonce, missing capability, and disabled preview all fail closed. */
	public function test_authorization_and_sensitive_preview_fail_closed(): void {
		$missing_nonce = new WP_REST_Request( 'POST', '/tagcore/v1/admin/tags/search' );
		$missing_nonce->set_body_params(
			array(
				'mode'   => 'tag_id',
				'tag_id' => '234567',
			)
		);
		self::assertSame( 403, rest_do_request( $missing_nonce )->get_status() );

		wp_set_current_user( $this->owner_id );
		self::assertSame(
			403,
			rest_do_request(
				$this->post(
					'/tagcore/v1/admin/tags/search',
					array(
						'mode'   => 'tag_id',
						'tag_id' => '234567',
					)
				)
			)->get_status()
		);

		wp_set_current_user( $this->administrator_id );
		$preview = $this->post( '/tagcore/v1/admin/finder-reports/' . $this->finder_report_id . '/reveal-message', array() );
		self::assertSame( 503, rest_do_request( $preview )->get_status() );

		global $wpdb;
		$tables = new TableNames( $wpdb->prefix );
		$query  = $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $tables->events() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above with a trusted identifier.
		self::assertSame( '0', $wpdb->get_var( $query ) );
	}

	/** Invalid calendar dates are rejected instead of rolling into another month. */
	public function test_invalid_calendar_filter_fails_closed(): void {
		$request = $this->post(
			'/tagcore/v1/admin/tags/search',
			array(
				'mode'           => 'tag_id',
				'tag_id'         => '234567',
				'activated_from' => '2026-02-31',
			)
		);

		self::assertSame( 400, rest_do_request( $request )->get_status() );
	}

	/** Existing indexes remain candidates for the new exact-anchor queries. */
	public function test_exact_anchor_queries_expose_approved_index_candidates(): void {
		global $wpdb;

		$tables  = new TableNames( $wpdb->prefix );
		$queries = array(
			'owner_id_status'       => $wpdb->prepare(
				'EXPLAIN SELECT tags.tag_id FROM %i tags INNER JOIN %i batches ON batches.batch_id = tags.batch_id WHERE tags.owner_id = %d ORDER BY tags.tag_id ASC LIMIT %d',
				$tables->tags(),
				$tables->batches(),
				$this->owner_id,
				50
			),
			'tag_status_created_at' => $wpdb->prepare(
				'EXPLAIN SELECT finder_report_id FROM %i WHERE tag_id = %s ORDER BY finder_report_id DESC LIMIT %d',
				$tables->finder_reports(),
				'234567',
				50
			),
			'user_email'            => $wpdb->prepare(
				'EXPLAIN SELECT ID FROM %i WHERE user_email = %s ORDER BY ID ASC LIMIT %d',
				$wpdb->users,
				$this->owner_email,
				2
			),
		);

		foreach ( $queries as $expected_index => $query ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared isolated EXPLAIN over trusted identifiers.
			$plan = $wpdb->get_results( $query, ARRAY_A );
			self::assertNotEmpty( $plan );
			$possible_keys = implode( ',', array_filter( array_column( $plan, 'possible_keys' ) ) );
			self::assertStringContainsString( $expected_index, $possible_keys );
		}
	}

	/**
	 * Create one authorized JSON-style POST request.
	 *
	 * @param string $route Internal REST route.
	 * @param array  $body Request body.
	 * @phpstan-param array<string, mixed> $body
	 */
	private function post( string $route, array $body ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params( $body );
		return $request;
	}

	/**
	 * Assert final private response controls.
	 *
	 * @param WP_REST_Response $response REST response.
	 * @param WP_REST_Request  $request REST request.
	 */
	private function assert_private_headers( WP_REST_Response $response, WP_REST_Request $request ): void {
		$filtered = apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request );
		self::assertInstanceOf( WP_REST_Response::class, $filtered );
		self::assertSame( 'no-store, private', $filtered->get_headers()['Cache-Control'] );
		self::assertSame( 'no-referrer', $filtered->get_headers()['Referrer-Policy'] );
		self::assertSame( 'nosniff', $filtered->get_headers()['X-Content-Type-Options'] );
	}

	/**
	 * Insert one owned Tag and one Finder Report with private bytes.
	 *
	 * @param wpdb $database Isolated WordPress test database.
	 */
	private function insert_fixtures( wpdb $database ): void {
		$tables = new TableNames( $database->prefix );
		$time   = '2026-08-13 08:00:00';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated fixture insert.
		self::assertSame(
			1,
			$database->insert(
				$tables->batches(),
				array(
					'batch_code'         => 'RT-326-OPS',
					'tag_type'           => 'classic_tag',
					'model_code'         => 'CLASSIC-OPS',
					'smart_network'      => 'none',
					'requested_quantity' => 1,
					'generated_quantity' => 1,
					'batch_status'       => 'released',
					'activation_enabled' => 1,
					'created_by'         => $this->administrator_id,
					'created_at'         => $time,
					'updated_at'         => $time,
				)
			)
		);
		$batch_id = (int) $database->insert_id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated fixture insert.
		self::assertSame(
			1,
			$database->insert(
				$tables->tags(),
				array(
					'tag_id'       => '234567',
					'batch_id'     => $batch_id,
					'owner_id'     => $this->owner_id,
					'tag_type'     => 'classic_tag',
					'model_code'   => 'CLASSIC-OPS',
					'item_name'    => 'PRIVATE ITEM',
					'public_label' => 'Public item',
					'tag_status'   => 'active',
					'lost_mode'    => 1,
					'lost_message' => 'PRIVATE LOST MESSAGE',
					'activated_at' => $time,
					'created_at'   => $time,
					'updated_at'   => $time,
				)
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated fixture insert.
		self::assertSame(
			1,
			$database->insert(
				$tables->finder_reports(),
				array(
					'tag_id'                    => '234567',
					'owner_id_at_submission'    => $this->owner_id,
					'message_ciphertext'        => 'PRIVATE MESSAGE CIPHERTEXT',
					'report_status'             => 'ready',
					'evidence_status'           => 'ready',
					'owner_notification_status' => 'queued',
					'expires_at'                => '2026-09-13 08:00:00',
					'created_at'                => $time,
					'updated_at'                => $time,
				)
			)
		);
		$this->finder_report_id = (int) $database->insert_id;
	}

	/**
	 * Apply the production Schema chain.
	 *
	 * @param wpdb $database Isolated WordPress test database.
	 */
	private function migrate( wpdb $database ): void {
		$runner = new MigrationRunner(
			( new MigrationRegistryFactory( $database ) )->create(),
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
		);
		self::assertSame( 13, $runner->migrate()->ending_version );
	}

	/**
	 * Drop only trusted ReturnTag tables from the isolated test database.
	 *
	 * @param wpdb $database Isolated WordPress test database.
	 */
	private function clear_schema( wpdb $database ): void {
		$tables = new TableNames( $database->prefix );
		foreach (
			array(
				$tables->tag_transfers(),
				$tables->finder_report_media(),
				$tables->finder_reports(),
				$tables->events(),
				$tables->access_tokens(),
				$tables->messages(),
				$tables->conversations(),
				$tables->auth_challenges(),
				$tables->batch_exports(),
				$tables->tags(),
				$tables->batches(),
			) as $table_name
		) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated cleanup with trusted names.
			$database->query( "DROP TABLE IF EXISTS {$table_name}" );
		}
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}
}
