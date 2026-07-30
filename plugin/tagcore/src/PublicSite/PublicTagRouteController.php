<?php
/**
 * WordPress public Tag route adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Tag\TagIdInputNormalizer;
use ReturnTag\TagCore\Domain\Tag\TagId;
use WP_Rewrite;

/**
 * Registers and serves the theme-independent RT-301 route.
 */
final class PublicTagRouteController {
	public const QUERY_VAR = 'returntag_tag_id';

	public const REWRITE_PATTERN = '^t/([^/]+)/?$';

	public const STYLE_HANDLE = 'returntag-tagcore-public';

	/**
	 * Create the route adapter.
	 *
	 * @param string                  $plugin_dir Absolute TagCore plugin directory.
	 * @param PublicTagResponsePolicy $responses Fail-closed HTTP policy.
	 * @param TagIdInputNormalizer    $tag_ids   Canonical public input boundary.
	 */
	public function __construct(
		private readonly string $plugin_dir,
		private readonly PublicTagResponsePolicy $responses,
		private readonly TagIdInputNormalizer $tag_ids
	) {
	}

	/**
	 * Register request-time WordPress hooks.
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_filter( 'redirect_canonical', array( $this, 'disable_canonical_redirect' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'prepare_response' ), 0 );
		add_filter( 'template_include', array( $this, 'select_template' ), PHP_INT_MAX );
	}

	/**
	 * Register the one-segment public Tag route.
	 */
	public function register_rewrite_rule(): void {
		add_rewrite_rule(
			self::REWRITE_PATTERN,
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Remove the route from the in-memory rewrite collection before deactivation flush.
	 */
	public function unregister_rewrite_rule(): void {
		global $wp_rewrite;

		if ( ! $wp_rewrite instanceof WP_Rewrite ) {
			return;
		}

		unset( $wp_rewrite->extra_rules_top[ self::REWRITE_PATTERN ] );
		unset( $wp_rewrite->extra_rules[ self::REWRITE_PATTERN ] );
	}

	/**
	 * Add the internal route value to WordPress query variables.
	 *
	 * @param array $query_vars Existing public query variables.
	 * @phpstan-param list<string> $query_vars
	 * @return list<string>
	 */
	public function register_query_var( array $query_vars ): array {
		$query_vars[] = self::QUERY_VAR;

		return array_values( array_unique( $query_vars ) );
	}

	/**
	 * Keep WordPress canonicalization from competing with TagCore's ID redirect.
	 *
	 * @param string|false $redirect_url Proposed canonical URL.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public function disable_canonical_redirect( string|false $redirect_url, string $requested_url ): string|false {
		unset( $requested_url );

		return $this->is_public_tag_request() ? false : $redirect_url;
	}

	/**
	 * Apply a generic fail-closed status and privacy controls.
	 */
	public function prepare_response(): void {
		if ( ! $this->is_public_tag_request() ) {
			return;
		}

		$method = $this->request_method();

		foreach ( $this->responses->headers_for_method( $method ) as $name => $value ) {
			header( $name . ': ' . $value, true );
		}

		$redirect_url = $this->canonical_redirect_url( $method );

		if ( null !== $redirect_url ) {
			wp_safe_redirect( $redirect_url, 301, 'TagCore' );
			exit;
		}

		status_header( $this->responses->status_for_method( $method ) );
		$this->enqueue_styles();
	}

	/**
	 * Return the validated canonical ID for the current route.
	 */
	public function normalized_tag_id(): ?TagId {
		$value = $this->raw_tag_id();

		if ( null === $value ) {
			return null;
		}

		try {
			return $this->tag_ids->normalize( rawurldecode( $value ) );
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * Return a canonical same-site URL only when a read request needs one.
	 *
	 * @param string|null $method Optional validated request method for tests and request handling.
	 */
	public function canonical_redirect_url( ?string $method = null ): ?string {
		$method = null === $method ? $this->request_method() : strtoupper( $method );

		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			return null;
		}

		$value  = $this->raw_tag_id();
		$tag_id = $this->normalized_tag_id();

		if ( null === $value || null === $tag_id || $value === $tag_id->value ) {
			return null;
		}

		return home_url( '/t/' . rawurlencode( $tag_id->value ) );
	}

	/**
	 * Select the standalone plugin template only for the ReturnTag route.
	 *
	 * @param string $template Theme-selected template.
	 */
	public function select_template( string $template ): string {
		if ( ! $this->is_public_tag_request() ) {
			return $template;
		}

		$public_template = $this->plugin_dir . '/templates/public/tag-unavailable.php';

		if ( is_readable( $public_template ) ) {
			return $public_template;
		}

		wp_die(
			esc_html__( 'ReturnTag is temporarily unavailable.', 'tagcore' ),
			esc_html__( 'ReturnTag unavailable', 'tagcore' ),
			array( 'response' => 500 )
		);
	}

	/**
	 * Determine whether WordPress resolved the public Tag route.
	 */
	public function is_public_tag_request(): bool {
		return null !== $this->raw_tag_id();
	}

	/**
	 * Return the captured untrusted route segment without exposing it.
	 */
	private function raw_tag_id(): ?string {
		$value = get_query_var( self::QUERY_VAR, null );

		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Enqueue only the TagCore public stylesheet for the standalone template.
	 */
	private function enqueue_styles(): void {
		$asset_file = $this->plugin_dir . '/build/public/public.ts.asset.php';
		$style_file = $this->plugin_dir . '/build/public/public.ts.css';

		if ( ! is_readable( $asset_file ) || ! is_readable( $style_file ) ) {
			return;
		}

		/**
		 * Compiled dependency metadata generated by @wordpress/scripts.
		 *
		 * @var mixed $asset
		 */
		$asset   = require $asset_file;
		$version = is_array( $asset ) && isset( $asset['version'] ) && is_string( $asset['version'] )
			? $asset['version']
			: RETURNTAG_TAGCORE_VERSION;

		wp_enqueue_style(
			self::STYLE_HANDLE,
			plugins_url( 'build/public/public.ts.css', $this->plugin_dir . '/tagcore.php' ),
			array(),
			$version
		);
	}

	/**
	 * Return one bounded uppercase request-method token.
	 */
	private function request_method(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Server method token is validated against a closed policy.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( $_SERVER['REQUEST_METHOD'] )
			: 'GET';

		return 1 === preg_match( '/^[A-Z]+$/D', $method ) ? $method : 'GET';
	}
}
