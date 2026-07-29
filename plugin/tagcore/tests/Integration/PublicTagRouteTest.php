<?php
/**
 * RT-301 public route integration coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\PublicSite\PublicRewriteLifecycle;
use ReturnTag\TagCore\PublicSite\PublicTagResponsePolicy;
use ReturnTag\TagCore\PublicSite\PublicTagRouteController;
use WP_Rewrite;
use WP_UnitTestCase;

/**
 * Exercises route registration, matching, lifecycle, and isolated rendering.
 */
final class PublicTagRouteTest extends WP_UnitTestCase {
	/**
	 * Route instance under test.
	 *
	 * @var PublicTagRouteController
	 */
	private PublicTagRouteController $route;

	/**
	 * Original test-site permalink structure.
	 *
	 * @var string
	 */
	private string $original_permalink_structure;

	/**
	 * Prepare a predictable permalink environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		global $wp_rewrite;

		self::assertInstanceOf( WP_Rewrite::class, $wp_rewrite );
		$this->original_permalink_structure = (string) $wp_rewrite->permalink_structure;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		$this->route = new PublicTagRouteController(
			RETURNTAG_TAGCORE_DIR,
			new PublicTagResponsePolicy()
		);

		$this->route->register_rewrite_rule();
		flush_rewrite_rules( false );
	}

	/**
	 * Remove the route from the persisted test rewrite collection.
	 */
	protected function tearDown(): void {
		global $wp_rewrite;

		$this->route->unregister_rewrite_rule();

		if ( $wp_rewrite instanceof WP_Rewrite ) {
			$wp_rewrite->set_permalink_structure( $this->original_permalink_structure );
		}

		flush_rewrite_rules( false );

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
	 * WordPress resolves the raw segment to the internal query variable.
	 */
	public function test_wordpress_resolves_the_public_route(): void {
		$this->go_to( home_url( '/t/a7-r2w9/' ) );

		self::assertSame( 'a7-r2w9', get_query_var( PublicTagRouteController::QUERY_VAR ) );
		self::assertTrue( $this->route->is_public_tag_request() );
	}

	/**
	 * RT-301 does not override unrelated theme routing.
	 */
	public function test_unrelated_requests_keep_the_theme_template(): void {
		$this->go_to( home_url( '/not-a-tag-route/' ) );

		self::assertFalse( $this->route->is_public_tag_request() );
		self::assertSame( '/theme/template.php', $this->route->select_template( '/theme/template.php' ) );
		self::assertSame(
			'https://example.test/canonical',
			$this->route->disable_canonical_redirect(
				'https://example.test/canonical',
				'https://example.test/not-a-tag-route'
			)
		);
	}

	/**
	 * The standalone fallback remains generic and never renders the raw Tag ID.
	 */
	public function test_route_selects_and_renders_the_plugin_owned_template(): void {
		$this->go_to( home_url( '/t/A7R2W9/' ) );
		$template = $this->route->select_template( '/theme/template.php' );

		self::assertSame(
			RETURNTAG_TAGCORE_DIR . '/templates/public/tag-unavailable.php',
			$template
		);
		self::assertFalse(
			$this->route->disable_canonical_redirect(
				'https://example.test/t/a7r2w9',
				'https://example.test/t/A7R2W9'
			)
		);

		ob_start();
		require $template;
		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString( '<main class="returntag-public__main">', $output );
		self::assertStringContainsString( 'Tag service is temporarily unavailable', $output );
		self::assertStringContainsString( 'Return to homepage', $output );
		self::assertStringContainsString( 'href="' . esc_url( home_url( '/' ) ) . '"', $output );
		self::assertStringNotContainsString( 'A7R2W9', $output );
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
		$network_rules = get_option( 'rewrite_rules', array() );
		self::assertSame( $rules, $network_rules );
	}
}
