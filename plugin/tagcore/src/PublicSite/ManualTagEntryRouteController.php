<?php
/**
 * WordPress manual Tag entry route adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use RuntimeException;
use WP_Rewrite;

/**
 * Registers and serves the theme-independent manual-entry routes.
 */
final class ManualTagEntryRouteController {
	public const QUERY_VAR = 'returntag_tag_entry_intent';

	public const REWRITE_PATTERN = '^tag/(activate|report)/?$';

	/**
	 * Create the route adapter.
	 *
	 * @param string                         $plugin_dir Absolute TagCore plugin directory.
	 * @param ManualTagEntryResponsePolicy   $responses HTTP response policy.
	 * @param ManualTagEntryFormHandler      $form_handler Public form boundary.
	 * @param ManualTagEntryTemplateRenderer $renderer Standalone renderer.
	 * @param TagEntryUrlProvider            $urls Same-site URL provider.
	 */
	public function __construct(
		private readonly string $plugin_dir,
		private readonly ManualTagEntryResponsePolicy $responses,
		private readonly ManualTagEntryFormHandler $form_handler,
		private readonly ManualTagEntryTemplateRenderer $renderer,
		private readonly TagEntryUrlProvider $urls
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
	}

	/**
	 * Register the two closed manual-entry paths.
	 */
	public function register_rewrite_rule(): void {
		add_rewrite_rule(
			self::REWRITE_PATTERN,
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Remove the entry route before deactivation flush.
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
	 * Add the internal entry intent to WordPress query variables.
	 *
	 * @param array $query_vars Existing query variables.
	 * @phpstan-param list<string> $query_vars
	 * @return list<string>
	 */
	public function register_query_var( array $query_vars ): array {
		$query_vars[] = self::QUERY_VAR;

		return array_values( array_unique( $query_vars ) );
	}

	/**
	 * Keep WordPress canonicalization from competing with TagCore.
	 *
	 * @param string|false $redirect_url Proposed redirect URL.
	 * @param string       $requested_url Requested URL.
	 */
	public function disable_canonical_redirect( string|false $redirect_url, string $requested_url ): string|false {
		unset( $requested_url );

		return null !== $this->intent() ? false : $redirect_url;
	}

	/**
	 * Validate, redirect, or render one manual-entry response.
	 */
	public function prepare_response(): void {
		$intent = $this->intent();

		if ( null === $intent ) {
			return;
		}

		$method     = $this->request_method();
		$submission = 'POST' === $method
			? $this->form_handler->submit()
			: new ManualTagEntrySubmission(
				in_array( $method, array( 'GET', 'HEAD' ), true )
					? ManualTagEntryFormState::READY
					: ManualTagEntryFormState::UNAVAILABLE
			);

		if ( null !== $submission->tag_id ) {
			$this->send_headers( $method, ManualTagEntryFormState::READY );
			wp_safe_redirect( $this->urls->canonical_tag_url( $submission->tag_id ), 303, 'TagCore' );
			exit;
		}

		$this->send_headers( $method, $submission->state );
		status_header( $this->responses->status_for( $method, $submission->state ) );
		$this->enqueue_assets();

		try {
			$this->renderer->render( $intent, $this->urls->entry_url( $intent ), $submission->state );
		} catch ( RuntimeException ) {
			wp_die(
				esc_html__( 'ForgeTag is temporarily unavailable.', 'tagcore' ),
				esc_html__( 'ForgeTag unavailable', 'tagcore' ),
				array( 'response' => 500 )
			);
		}

		exit;
	}

	/**
	 * Return the closed intent for the current route.
	 */
	public function intent(): ?TagEntryIntent {
		$value = get_query_var( self::QUERY_VAR, null );

		return is_string( $value ) ? TagEntryIntent::tryFrom( $value ) : null;
	}

	/**
	 * Enqueue only TagCore-owned entry assets.
	 */
	private function enqueue_assets(): void {
		TagEntryLinkBlock::register_assets( $this->plugin_dir );
		wp_enqueue_style( TagEntryLinkBlock::STYLE_HANDLE );
		wp_enqueue_script_module( TagEntryLinkBlock::SCRIPT_MODULE_HANDLE );
	}

	/**
	 * Emit approved privacy-safe response headers.
	 *
	 * @param string                  $method HTTP request method.
	 * @param ManualTagEntryFormState $state Safe form state.
	 */
	private function send_headers( string $method, ManualTagEntryFormState $state ): void {
		foreach ( $this->responses->headers_for( $method, $state ) as $name => $value ) {
			header( $name . ': ' . $value, true );
		}
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
