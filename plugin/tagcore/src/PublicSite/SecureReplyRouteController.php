<?php
/**
 * Secure Reply public route.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\PublicSite;

use ReturnTag\TagCore\Application\Conversation\ConversationSafetyAction;
use ReturnTag\TagCore\Infrastructure\Conversation\ConversationRelayRuntime;
use WP;
use WP_Rewrite;

/** Keeps bearer links out of rendered URLs and performs mutations only by POST. */
final readonly class SecureReplyRouteController {
	public const QUERY_VAR        = 'returntag_secure_reply';
	public const REWRITE_PATTERN  = '^secure-reply/?$';
	private const LINK_COOKIE     = 'returntag_reply_link';
	private const SESSION_COOKIE  = 'returntag_reply_session';
	private const TERMINAL_COOKIE = 'returntag_reply_terminal';
	private const FEEDBACK_COOKIE = 'returntag_reply_feedback';
	private const FEEDBACK_SENT   = 'sent';
	private const FEEDBACK_FAILED = 'failed';
	/**
	 * Create the route adapter.
	 *
	 * @param string                        $plugin_dir Plugin directory.
	 * @param ConversationRelayRuntime|null $runtime Optional runtime.
	 * @param PublicFormRequestGuard        $guard Request guard.
	 */
	public function __construct( private string $plugin_dir, private ?ConversationRelayRuntime $runtime, private PublicFormRequestGuard $guard ) {}
	/** Register request-time hooks. */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_filter( 'redirect_canonical', array( $this, 'disable_canonical_redirect' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'prepare_response' ), 0 ); }
	/** Register the closed path. */
	public function register_rewrite_rule(): void {
		add_rewrite_rule( self::REWRITE_PATTERN, 'index.php?' . self::QUERY_VAR . '=1', 'top' ); }
	/** Remove the route before rewrite flushing. */
	public function unregister_rewrite_rule(): void {
		global $wp_rewrite;
		if ( $wp_rewrite instanceof WP_Rewrite ) {
			unset( $wp_rewrite->extra_rules_top[ self::REWRITE_PATTERN ], $wp_rewrite->extra_rules[ self::REWRITE_PATTERN ] );} }
	/**
	 * Add the internal query variable.
	 *
	 * @param string[] $vars Existing variables.
	 * @return list<string>
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return array_values( array_unique( $vars ) );}
	/**
	 * Disable canonical redirects for this path.
	 *
	 * @param string|false $url Proposed URL.
	 * @param string       $requested Requested URL.
	 */
	public function disable_canonical_redirect( string|false $url, string $requested ): string|false {
		unset( $requested );
		return $this->is_route() ? false : $url;}
	/** Redirect bearer URLs or render a sensitive response. */
	public function prepare_response(): void {
		if ( ! $this->is_route() ) {
			return;
		} $this->headers();
		$method = $this->method();
		if ( 'GET' === $method ) {
			$token = $this->query_token();
			if ( null !== $token ) {
				$this->cookie( self::LINK_COOKIE, $token, time() + 300 );
				wp_safe_redirect( home_url( '/secure-reply/' ), 303, 'TagCore' );
				exit;}
		}
		if ( 'POST' === $method ) {
			$this->post();
			exit;}
		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			status_header( 405 );
			header( 'Allow: GET, HEAD, POST', true );
			exit;}
		$link    = $this->cookie_value( self::LINK_COOKIE );
		$session = $this->cookie_value( self::SESSION_COOKIE );
		$thread  = null;
		if ( null !== $session && null !== $this->runtime ) {
			try {
				$thread = $this->runtime->read_thread->execute( $session );
			} catch ( \Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Dependency failures converge to the generic unavailable state.
				$thread = null;
			}
		}
		$terminal = $this->terminal_cookie();
		if ( $terminal ) {
			$this->clear( self::TERMINAL_COOKIE );
		}
		$feedback = $this->feedback_cookie();
		if ( null !== $feedback ) {
			$this->clear( self::FEEDBACK_COOKIE );
		}
		$status = $terminal ? 'terminal' : ( null === $this->runtime || ( null === $link && null === $thread ) ? 'unavailable' : ( null !== $thread ? 'thread' : 'continue' ) );
		status_header( 'unavailable' === $status ? 404 : 200 );
		$this->enqueue_assets();
		$view = array(
			'status'   => $status,
			'thread'   => $thread,
			'nonce'    => wp_create_nonce( 'returntag_secure_reply' ),
			'action'   => home_url( '/secure-reply/' ),
			'feedback' => $feedback,
		);
		if ( 'HEAD' !== $method ) {
			require $this->plugin_dir . '/templates/public/secure-reply.php';
		} exit;
	}
	/** Process one nonce-protected same-site mutation. */
	private function post(): void {
		if ( null === $this->runtime || ! $this->guard->is_same_site() || ! $this->guard->valid_nonce( '_returntag_nonce', 'returntag_secure_reply' ) ) {
			$this->redirect();}
		$action = $this->guard->post_string( 'returntag_action', 32 );
		if ( 'exchange' === $action ) {
			$link    = $this->cookie_value( self::LINK_COOKIE );
			$session = null === $link ? null : $this->runtime->exchange_link->execute( $link );
			$this->clear( self::LINK_COOKIE );
			if ( null !== $session ) {
				$this->cookie( self::SESSION_COOKIE, $session, time() + 1800 );
			} $this->redirect();}
		if ( 'message' === $action ) {
			$session = $this->cookie_value( self::SESSION_COOKIE );
			$sent    = false;
			if ( null !== $session ) {
				try {
					$sent = $this->runtime->submit_message->execute( $session, $this->guard->post_string( 'message', 2000 ), $this->guard->direct_peer_ip() );
				} catch ( \Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Failure is intentionally mapped to a generic redirect.
					// Privacy-safe redirect.
				}
			}
			$this->cookie( self::FEEDBACK_COOKIE, $sent ? self::FEEDBACK_SENT : self::FEEDBACK_FAILED, time() + 300 );
			$this->redirect();
		}
		$safety_action = ConversationSafetyAction::tryFrom( $action );
		if ( null !== $safety_action && '1' === $this->guard->post_string( 'confirm_terminal_action', 1 ) ) {
			$session = $this->cookie_value( self::SESSION_COOKIE );
			if ( null !== $session && $this->runtime->safety_action->execute( $session, $safety_action ) ) {
				$this->clear( self::LINK_COOKIE );
				$this->clear( self::SESSION_COOKIE );
				$this->cookie( self::TERMINAL_COOKIE, '1', time() + 300 );
			}
			$this->redirect();
		}
		$this->redirect();
	}
	/** Redirect to the clean path. */
	private function redirect(): never {
		wp_safe_redirect( home_url( '/secure-reply/' ), 303, 'TagCore' );
		exit;}
	/** Determine whether WordPress matched this route. */
	private function is_route(): bool {
		global $wp;
		return $wp instanceof WP && self::REWRITE_PATTERN === $wp->matched_rule;}
	/** Read one closed request method. */
	private function method(): string {
		$value = $_SERVER['REQUEST_METHOD'] ?? 'GET';
		return is_string( $value ) && 1 === preg_match( '/^[A-Z]+$/D', $value ) ? $value : 'GET';}
	/** Read one valid GET bearer without consuming it. */
	private function query_token(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET performs no database mutation; it only strips the bearer from the URL.
		$value = $_GET['token'] ?? null;
		if ( ! is_string( $value ) ) {
			return null;
		} $value = wp_unslash( $value );
		return 1 === preg_match( '/^[A-Za-z0-9_-]{43}$/D', $value ) ? $value : null;}
	/**
	 * Read one structurally valid cookie.
	 *
	 * @param string $name Cookie name.
	 */
	private function cookie_value( string $name ): ?string {
		$value = $_COOKIE[ $name ] ?? null;
		return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_-]{43}$/D', $value ) ? $value : null;}

	/** Return whether the generic terminal flash is present. */
	private function terminal_cookie(): bool {
		return '1' === ( $_COOKIE[ self::TERMINAL_COOKIE ] ?? null );
	}

	/** Return one closed, non-sensitive message feedback code. */
	private function feedback_cookie(): ?string {
		$value = $_COOKIE[ self::FEEDBACK_COOKIE ] ?? null;
		return is_string( $value ) && in_array( $value, array( self::FEEDBACK_SENT, self::FEEDBACK_FAILED ), true ) ? $value : null;
	}
	/**
	 * Set one scoped secure cookie.
	 *
	 * @param string $name Cookie name.
	 * @param string $value Cookie value.
	 * @param int    $expires Expiry timestamp.
	 */
	private function cookie( string $name, string $value, int $expires ): void {
		setcookie(
			$name,
			$value,
			array(
				'expires'  => $expires,
				'path'     => '/secure-reply/',
				'secure'   => true,
				'httponly' => true,
				'samesite' => 'Strict',
			)
		);}
	/**
	 * Expire one scoped secure cookie.
	 *
	 * @param string $name Cookie name.
	 */
	private function clear( string $name ): void {
		setcookie(
			$name,
			'',
			array(
				'expires'  => time() - 3600,
				'path'     => '/secure-reply/',
				'secure'   => true,
				'httponly' => true,
				'samesite' => 'Strict',
			)
		);}
	/** Enqueue the existing TagCore stylesheet. */
	private function enqueue_assets(): void {
		$asset_file = $this->plugin_dir . '/build/public/public.ts.asset.php';
		$style_file = $this->plugin_dir . '/build/public/public.ts.css';
		if ( ! is_readable( $asset_file ) || ! is_readable( $style_file ) ) {
			return;}
		$asset   = require $asset_file;
		$version = is_array( $asset ) && isset( $asset['version'] ) && is_string( $asset['version'] ) ? $asset['version'] : RETURNTAG_TAGCORE_VERSION;
		wp_enqueue_style( PublicTagRouteController::STYLE_HANDLE, plugins_url( 'build/public/public.ts.css', $this->plugin_dir . '/tagcore.php' ), array(), $version );
	}
	/** Emit the sensitive-page response policy. */
	private function headers(): void {
		foreach ( array( 'Cache-Control: no-store, private, max-age=0', 'Pragma: no-cache', 'Referrer-Policy: no-referrer', 'X-Robots-Tag: noindex, nofollow, noarchive', 'X-Content-Type-Options: nosniff', 'Content-Security-Policy: default-src \'none\'; style-src \'self\'; form-action \'self\'; base-uri \'none\'; frame-ancestors \'none\'' ) as $header ) {
			header( $header, true );}}
}
