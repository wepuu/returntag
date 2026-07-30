<?php
/**
 * RT-301 through RT-303 public route integration coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPagePolicy;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPageState;
use ReturnTag\TagCore\Application\PublicTag\ResolvePublicTagPage;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailabilityPolicy;
use ReturnTag\TagCore\Application\Tag\TagIdInputNormalizer;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\SchemaState;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbPublicTagStateReader;
use ReturnTag\TagCore\Infrastructure\WordPressOptionFeatureFlagReader;
use ReturnTag\TagCore\PublicSite\PublicRewriteLifecycle;
use ReturnTag\TagCore\PublicSite\PublicTagResponsePolicy;
use ReturnTag\TagCore\PublicSite\PublicTagRouteController;
use ReturnTag\TagCore\PublicSite\PublicTagTemplateRenderer;
use WP_Rewrite;
use WP_UnitTestCase;
use wpdb;

/**
 * Exercises canonical routing, state resolution, privacy, and isolated rendering.
 */
final class PublicTagRouteTest extends WP_UnitTestCase {
	/**
	 * Route instance under test.
	 *
	 * @var PublicTagRouteController
	 */
	private PublicTagRouteController $route;

	/**
	 * Standalone renderer under test.
	 *
	 * @var PublicTagTemplateRenderer
	 */
	private PublicTagTemplateRenderer $renderer;

	/**
	 * Trusted product table names.
	 *
	 * @var TableNames
	 */
	private TableNames $tables;

	/**
	 * Original test-site permalink structure.
	 *
	 * @var string
	 */
	private string $original_permalink_structure;

	/**
	 * Build a current Schema and predictable permalink environment.
	 */
	protected function setUp(): void {
		global $wpdb, $wp_rewrite;

		parent::setUp();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		self::assertInstanceOf( wpdb::class, $wpdb );
		self::assertInstanceOf( WP_Rewrite::class, $wp_rewrite );

		$this->clear_schema( $wpdb );
		$registry = ( new MigrationRegistryFactory( $wpdb ) )->create();
		$runner   = new MigrationRunner(
			$registry,
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $wpdb, get_current_blog_id(), 0 )
		);
		self::assertSame( 8, $runner->migrate()->ending_version );

		update_option( FeatureFlag::GLOBAL_ACTIVATION->value, '1', false );
		update_option( FeatureFlag::FINDER_CONTACT->value, '1', false );

		$this->tables   = new TableNames( $wpdb->prefix );
		$gateway        = new WpdbGateway( $wpdb );
		$states         = new WpdbPublicTagStateReader( $gateway, $this->tables, new DatabaseDateTimeCodec() );
		$pages          = new ResolvePublicTagPage(
			$states,
			new WordPressOptionFeatureFlagReader(),
			new PublicTagPagePolicy( new TagActivationAvailabilityPolicy() )
		);
		$schema_state   = new SchemaState( new WordPressSchemaVersionStore(), $registry );
		$this->renderer = new PublicTagTemplateRenderer( RETURNTAG_TAGCORE_DIR );
		$this->route    = new PublicTagRouteController(
			RETURNTAG_TAGCORE_DIR,
			new PublicTagResponsePolicy(),
			new TagIdInputNormalizer(),
			$pages,
			$schema_state,
			$this->renderer
		);

		$this->original_permalink_structure = (string) $wp_rewrite->permalink_structure;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$this->route->register_rewrite_rule();
		flush_rewrite_rules( false );
	}

	/**
	 * Remove only isolated fixtures, flags, and rewrite state.
	 */
	protected function tearDown(): void {
		global $wpdb, $wp_rewrite;

		$this->route->unregister_rewrite_rule();

		if ( $wp_rewrite instanceof WP_Rewrite ) {
			$wp_rewrite->set_permalink_structure( $this->original_permalink_structure );
		}

		flush_rewrite_rules( false );
		delete_option( FeatureFlag::GLOBAL_ACTIVATION->value );
		delete_option( FeatureFlag::FINDER_CONTACT->value );
		wp_set_current_user( 0 );
		$this->clear_schema( $wpdb );

		parent::tearDown();
	}

	/**
	 * The rewrite accepts exactly one non-empty path segment.
	 */
	public function test_route_matches_exactly_one_tag_segment(): void {
		self::assertSame( 1, preg_match( '#^' . PublicTagRouteController::REWRITE_PATTERN . '#', 't/A7R2W9' ) );
		self::assertSame( 1, preg_match( '#^' . PublicTagRouteController::REWRITE_PATTERN . '#', 't/raw-value/' ) );
		self::assertSame( 0, preg_match( '#^' . PublicTagRouteController::REWRITE_PATTERN . '#', 't/' ) );
		self::assertSame( 0, preg_match( '#^' . PublicTagRouteController::REWRITE_PATTERN . '#', 't/A7R2W9/extra' ) );
	}

	/**
	 * WordPress resolves and canonicalizes the raw route segment.
	 */
	public function test_wordpress_resolves_the_public_route(): void {
		$this->go_to( home_url( '/t/a7-r2w9/' ) );

		self::assertSame( 'a7-r2w9', get_query_var( PublicTagRouteController::QUERY_VAR ) );
		self::assertTrue( $this->route->is_public_tag_request() );
		self::assertSame( 'A7R2W9', $this->route->normalized_tag_id()?->value );
		self::assertSame( home_url( '/t/A7R2W9' ), $this->route->canonical_redirect_url( 'GET' ) );
		self::assertSame( home_url( '/t/A7R2W9' ), $this->route->canonical_redirect_url( 'HEAD' ) );
		self::assertNull( $this->route->canonical_redirect_url( 'POST' ) );
	}

	/**
	 * Invalid input returns the generic invalid page without a database query.
	 */
	public function test_invalid_input_does_not_query_or_disclose_validation_detail(): void {
		global $wpdb;

		$this->go_to( home_url( '/t/A7R2W0/' ) );
		$before = $wpdb->num_queries;
		$page   = $this->route->resolve_page();

		self::assertSame( $before, $wpdb->num_queries );
		self::assertSame( PublicTagPageState::INVALID, $page->state );
		self::assertNull( $this->route->canonical_redirect_url( 'GET' ) );
		self::assertStringNotContainsString( 'A7R2W0', $this->renderer->render_to_string( $page ) );
	}

	/**
	 * Unknown canonical IDs use the same privacy-minimized invalid page.
	 */
	public function test_unknown_tag_is_invalid(): void {
		$this->go_to( home_url( '/t/A7R2W9/' ) );

		$page = $this->route->resolve_page();

		self::assertSame( PublicTagPageState::INVALID, $page->state );
		self::assertStringContainsString( 'We could not find this ReturnTag', $this->renderer->render_to_string( $page ) );
	}

	/**
	 * Unregistered Tags reflect release and activation controls.
	 */
	public function test_unregistered_tag_state_uses_batch_and_global_controls(): void {
		$this->insert_tag( 'A7R2W9', 'unregistered', 'released', true );
		$this->go_to( home_url( '/t/A7R2W9/' ) );

		self::assertSame( PublicTagPageState::ACTIVATION_ENTRY, $this->route->resolve_page()->state );

		update_option( FeatureFlag::GLOBAL_ACTIVATION->value, '0', false );
		self::assertSame( PublicTagPageState::ACTIVATION_UNAVAILABLE, $this->route->resolve_page()->state );
	}

	/**
	 * Active owner identity comes only from the server-side WordPress session.
	 */
	public function test_active_owner_and_finder_experiences_are_separated(): void {
		$owner_id = self::factory()->user->create();
		$this->insert_tag(
			'A7R2W9',
			'active',
			'suspended',
			false,
			$owner_id,
			'2026-07-30 00:00:00',
			'Blue backpack',
			true,
			'Please leave it with airport security.'
		);
		$this->go_to( home_url( '/t/A7R2W9/' ) );

		wp_set_current_user( $owner_id );
		$owner_page = $this->route->resolve_page();
		self::assertSame( PublicTagPageState::OWNER_ENTRY, $owner_page->state );
		self::assertNull( $owner_page->public_label );

		wp_set_current_user( 0 );
		$finder_page = $this->route->resolve_page();
		self::assertSame( PublicTagPageState::FINDER_ENTRY, $finder_page->state );
		self::assertSame( 'Blue backpack', $finder_page->public_label );
		self::assertSame( 'Please leave it with airport security.', $finder_page->lost_message );

		$html = $this->renderer->render_to_string( $finder_page );
		self::assertStringContainsString( 'Blue backpack', $html );
		self::assertStringContainsString( 'Marked as lost', $html );
		self::assertStringNotContainsString( (string) $owner_id, $html );
		self::assertStringNotContainsString( 'A7R2W9', $html );
	}

	/**
	 * Finder pause removes public item and Lost Mode content.
	 */
	public function test_finder_flag_fails_closed_without_optional_public_fields(): void {
		$this->insert_tag(
			'A7R2W9',
			'active',
			'released',
			true,
			42,
			'2026-07-30 00:00:00',
			'Camera',
			true,
			'Leave it at the front desk.'
		);
		update_option( FeatureFlag::FINDER_CONTACT->value, '0', false );
		$this->go_to( home_url( '/t/A7R2W9/' ) );

		$page = $this->route->resolve_page();
		$html = $this->renderer->render_to_string( $page );

		self::assertSame( PublicTagPageState::FINDER_UNAVAILABLE, $page->state );
		self::assertStringNotContainsString( 'Camera', $html );
		self::assertStringNotContainsString( 'front desk', $html );
	}

	/**
	 * Public text is escaped at render time and private fields are never selected.
	 */
	public function test_finder_content_is_escaped_without_private_item_name(): void {
		$this->insert_tag(
			'A7R2W9',
			'active',
			'released',
			true,
			42,
			'2026-07-30 00:00:00',
			'<script>alert("label")</script>',
			true,
			'<img src=x onerror=alert("lost")>'
		);
		$this->go_to( home_url( '/t/A7R2W9/' ) );

		$html = $this->renderer->render_to_string( $this->route->resolve_page() );

		self::assertStringContainsString( '&lt;script&gt;alert(&quot;label&quot;)&lt;/script&gt;', $html );
		self::assertStringContainsString( '&lt;img src=x onerror=alert(&quot;lost&quot;)&gt;', $html );
		self::assertStringNotContainsString( '<script>', $html );
		self::assertStringNotContainsString( '<img src=x', $html );
		self::assertStringNotContainsString( 'PRIVATE-ITEM-NAME', $html );
	}

	/**
	 * Tag-level service states remain distinct.
	 */
	public function test_suspended_and_retired_states_are_distinct(): void {
		$this->insert_tag( 'A7R2W8', 'suspended', 'released', true );
		$this->insert_tag( 'A7R2W9', 'retired', 'released', true );

		$this->go_to( home_url( '/t/A7R2W8/' ) );
		self::assertSame( PublicTagPageState::SUSPENDED, $this->route->resolve_page()->state );

		$this->go_to( home_url( '/t/A7R2W9/' ) );
		self::assertSame( PublicTagPageState::RETIRED, $this->route->resolve_page()->state );
	}

	/**
	 * Missing Batch or stale Schema returns the generic service page.
	 */
	public function test_data_and_schema_inconsistency_fail_closed(): void {
		global $wpdb;

		$wpdb->insert(
			$this->tables->tags(),
			$this->tag_row( 'A7R2W9', 999999, 'unregistered' ),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		$this->go_to( home_url( '/t/A7R2W9/' ) );
		self::assertSame( PublicTagPageState::SERVICE_UNAVAILABLE, $this->route->resolve_page()->state );

		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
		self::assertSame( PublicTagPageState::SERVICE_UNAVAILABLE, $this->route->resolve_page()->state );
	}

	/**
	 * RT-301 does not override unrelated theme canonicalization.
	 */
	public function test_unrelated_requests_keep_theme_canonicalization(): void {
		$this->go_to( home_url( '/not-a-tag-route/' ) );

		self::assertFalse( $this->route->is_public_tag_request() );
		self::assertSame(
			'https://example.test/canonical',
			$this->route->disable_canonical_redirect(
				'https://example.test/canonical',
				'https://example.test/not-a-tag-route'
			)
		);
	}

	/**
	 * Lifecycle activation and deactivation persist and remove the exact route.
	 */
	public function test_lifecycle_refreshes_rewrite_rules_without_network_mutation(): void {
		$lifecycle = new PublicRewriteLifecycle( RETURNTAG_TAGCORE_FILE, $this->route );

		$lifecycle->deactivate();
		$rules = get_option( 'rewrite_rules', array() );
		self::assertIsArray( $rules );
		self::assertArrayNotHasKey( PublicTagRouteController::REWRITE_PATTERN, $rules );

		$lifecycle->activate();
		$rules = get_option( 'rewrite_rules', array() );
		self::assertIsArray( $rules );
		self::assertArrayHasKey( PublicTagRouteController::REWRITE_PATTERN, $rules );

		$lifecycle->activate( true );
		self::assertSame( $rules, get_option( 'rewrite_rules', array() ) );
	}

	/**
	 * Insert one synthetic Batch and Tag fixture.
	 *
	 * @param string      $tag_id Canonical Tag ID.
	 * @param string      $tag_status Canonical Tag status.
	 * @param string      $batch_status Canonical Batch status.
	 * @param bool        $batch_activation_enabled Batch activation control.
	 * @param int|null    $owner_id Optional owner ID.
	 * @param string|null $activated_at Optional activation timestamp.
	 * @param string|null $public_label Optional public label.
	 * @param bool        $lost_mode Lost Mode state.
	 * @param string|null $lost_message Optional Lost Mode message.
	 */
	private function insert_tag(
		string $tag_id,
		string $tag_status,
		string $batch_status,
		bool $batch_activation_enabled,
		?int $owner_id = null,
		?string $activated_at = null,
		?string $public_label = null,
		bool $lost_mode = false,
		?string $lost_message = null
	): void {
		global $wpdb;

		$wpdb->insert(
			$this->tables->batches(),
			array(
				'batch_code'         => 'RT303-' . $tag_id,
				'tag_type'           => 'classic_tag',
				'model_code'         => null,
				'smart_network'      => 'none',
				'manufacturer'       => null,
				'sales_channel'      => null,
				'requested_quantity' => 1,
				'generated_quantity' => 1,
				'batch_status'       => $batch_status,
				'activation_enabled' => $batch_activation_enabled ? 1 : 0,
				'notes'              => null,
				'created_by'         => 1,
				'created_at'         => '2026-07-30 00:00:00',
				'updated_at'         => '2026-07-30 00:00:00',
			)
		);
		self::assertGreaterThan( 0, $wpdb->insert_id );

		$row                 = $this->tag_row( $tag_id, $wpdb->insert_id, $tag_status );
		$row['owner_id']     = $owner_id;
		$row['activated_at'] = $activated_at;
		$row['public_label'] = $public_label;
		$row['lost_mode']    = $lost_mode ? 1 : 0;
		$row['lost_message'] = $lost_message;
		$row['item_name']    = 'PRIVATE-ITEM-NAME';

		self::assertSame( 1, $wpdb->insert( $this->tables->tags(), $row ) );
	}

	/**
	 * Build one required Tag row with no real personal data.
	 *
	 * @param string $tag_id Canonical Tag ID.
	 * @param int    $batch_id Stored Batch ID.
	 * @param string $tag_status Canonical Tag status.
	 * @return array<string, int|string|null>
	 */
	private function tag_row( string $tag_id, int $batch_id, string $tag_status ): array {
		return array(
			'tag_id'     => $tag_id,
			'batch_id'   => $batch_id,
			'tag_type'   => 'classic_tag',
			'tag_status' => $tag_status,
			'lost_mode'  => 0,
			'created_at' => '2026-07-30 00:00:00',
			'updated_at' => '2026-07-30 00:00:00',
			'item_name'  => 'PRIVATE-ITEM-NAME',
		);
	}

	/**
	 * Remove only trusted ReturnTag tables from the isolated test database.
	 *
	 * @param wpdb $database Database adapter.
	 */
	private function clear_schema( wpdb $database ): void {
		$names = new TableNames( $database->prefix );

		foreach ( array( $names->events(), $names->access_tokens(), $names->messages(), $names->conversations(), $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated cleanup with trusted identifiers.
			$database->query( "DROP TABLE IF EXISTS {$table_name}" );
		}

		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}
}
