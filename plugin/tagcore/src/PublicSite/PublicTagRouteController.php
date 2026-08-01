<?php
/**
 * WordPress public Tag route adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceException;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPage;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPageState;
use ReturnTag\TagCore\Application\PublicTag\ResolvePublicTagPage;
use ReturnTag\TagCore\Application\Tag\TagIdInputNormalizer;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Infrastructure\Migration\SchemaState;
use RuntimeException;
use WP;
use WP_Rewrite;

/**
 * Registers and serves the theme-independent public Tag route.
 */
final class PublicTagRouteController {
	public const QUERY_VAR = 'returntag_tag_id';

	public const REWRITE_PATTERN = '^t/([^/]+)/?$';

	public const STYLE_HANDLE = 'returntag-tagcore-public';

	/**
	 * Create the route adapter.
	 *
	 * @param string                    $plugin_dir Absolute TagCore plugin directory.
	 * @param PublicTagResponsePolicy   $responses Fail-closed HTTP policy.
	 * @param TagIdInputNormalizer      $tag_ids   Canonical public input boundary.
	 * @param ResolvePublicTagPage      $pages Public page use case.
	 * @param SchemaState               $schema_state Current Schema readiness.
	 * @param PublicTagTemplateRenderer $renderer Standalone page renderer.
	 * @param ActivationOtpFormHandler  $activation_form Activation OTP form boundary.
	 */
	public function __construct(
		private readonly string $plugin_dir,
		private readonly PublicTagResponsePolicy $responses,
		private readonly TagIdInputNormalizer $tag_ids,
		private readonly ResolvePublicTagPage $pages,
		private readonly SchemaState $schema_state,
		private readonly PublicTagTemplateRenderer $renderer,
		private readonly ActivationOtpFormHandler $activation_form
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
	 * Resolve and render the standalone public response.
	 */
	public function prepare_response(): void {
		if ( ! $this->is_public_tag_request() ) {
			return;
		}

		$method = $this->request_method();

		$redirect_url = $this->canonical_redirect_url( $method );

		if ( null !== $redirect_url ) {
			foreach ( $this->responses->headers_for_method( $method ) as $name => $value ) {
				header( $name . ': ' . $value, true );
			}

			wp_safe_redirect( $redirect_url, 301, 'TagCore' );
			exit;
		}

		$page            = in_array( $method, array( 'GET', 'HEAD', 'POST' ), true )
			? $this->resolve_page()
			: PublicTagPage::service_unavailable();
		$tag_id          = $this->normalized_tag_id();
		$activation_post = 'POST' === $method
			&& PublicTagPageState::ACTIVATION_ENTRY === $page->state
			&& null !== $tag_id;
		$authenticated   = $this->activation_form->is_authenticated();
		$form_state      = $authenticated
			? ActivationOtpFormState::AUTHENTICATED
			: ( $activation_post
				? $this->activation_form->submit( $tag_id )
				: ActivationOtpFormState::READY );

		if ( $activation_post && $authenticated && $this->activation_form->is_activation_action() ) {
			$attempt = $this->activation_form->activate( $tag_id );

			if ( null === $attempt || PublicTagPageState::ACTIVATION_ENTRY === $attempt->page->state ) {
				$form_state = ActivationOtpFormState::ACTIVATION_ERROR;
			} else {
				$page = $attempt->page;
			}
		}

		if (
			$activation_post
			&& (
				( $authenticated && ! $this->activation_form->is_activation_action() )
				|| PublicTagPageState::ACTIVATION_ENTRY !== $page->state
			)
		) {
			foreach ( $this->responses->headers_for_method( $method, true ) as $name => $value ) {
				header( $name . ': ' . $value, true );
			}

			wp_safe_redirect(
				home_url( '/t/' . rawurlencode( $tag_id->value ) ),
				303,
				'TagCore'
			);
			exit;
		}
		$form = PublicTagPageState::ACTIVATION_ENTRY === $page->state && null !== $tag_id
			? new ActivationOtpFormView(
				home_url( '/t/' . rawurlencode( $tag_id->value ) ),
				wp_create_nonce( ActivationOtpFormHandler::NONCE_ACTION ),
				$form_state
			)
			: null;

		foreach ( $this->responses->headers_for_method( $method, $activation_post ) as $name => $value ) {
			header( $name . ': ' . $value, true );
		}

		status_header( $this->responses->status_for( $method, $page->state, $activation_post ) );
		$this->enqueue_styles();

		try {
			$this->renderer->render( $page, $form );
		} catch ( RuntimeException ) {
			wp_die(
				esc_html__( 'ReturnTag is temporarily unavailable.', 'tagcore' ),
				esc_html__( 'ReturnTag unavailable', 'tagcore' ),
				array( 'response' => 500 )
			);
		}

		exit;
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
	 * Resolve the current route without leaking failures or stored private data.
	 */
	public function resolve_page(): PublicTagPage {
		$tag_id = $this->normalized_tag_id();

		if ( null === $tag_id ) {
			return PublicTagPage::invalid();
		}

		if ( ! $this->schema_state->is_current() ) {
			return PublicTagPage::service_unavailable();
		}

		$current_user_id = get_current_user_id();

		try {
			return $this->pages->execute(
				$tag_id,
				$current_user_id > 0 ? $current_user_id : null
			);
		} catch ( PersistenceException ) {
			return PublicTagPage::service_unavailable();
		}
	}

	/**
	 * Determine whether WordPress resolved the public Tag route.
	 */
	public function is_public_tag_request(): bool {
		global $wp;

		return $wp instanceof WP
			&& self::REWRITE_PATTERN === $wp->matched_rule
			&& null !== $this->raw_tag_id();
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
