<?php
/**
 * WordPress authenticated session adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Auth;

use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use RuntimeException;
use Throwable;
use WP_User;

/**
 * Issues a fresh WordPress session with explicit SameSite cookie attributes.
 */
final class WordPressAuthenticatedSession implements AuthenticatedSession {
	/**
	 * Cookie side-effect boundary.
	 *
	 * @var AuthenticationCookieEmitter
	 */
	private readonly AuthenticationCookieEmitter $cookies;

	/**
	 * Create the native session adapter.
	 *
	 * @param AuthenticationCookieEmitter|null $cookies Optional testable cookie boundary.
	 */
	public function __construct( ?AuthenticationCookieEmitter $cookies = null ) {
		$this->cookies = $cookies ?? new PhpAuthenticationCookieEmitter();
	}

	/**
	 * Return the current authenticated WordPress User ID.
	 */
	public function current_user_id(): ?int {
		$user_id = get_current_user_id();

		return $user_id > 0 ? $user_id : null;
	}

	/**
	 * Establish a fresh, non-persistent native WordPress session.
	 *
	 * @param int $user_id Positive WordPress User ID.
	 * @throws RuntimeException When identity validation or session issuance fails.
	 * @throws Throwable When a cookie emitter raises an unexpected failure.
	 */
	public function authenticate( int $user_id ): void {
		if ( $user_id < 1 || $this->cookies->headers_sent() ) {
			throw new RuntimeException( 'Passwordless session cannot be established.' );
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user instanceof WP_User ) {
			throw new RuntimeException( 'Passwordless session cannot be established.' );
		}

		$captured_auth      = null;
		$captured_logged_in = null;
		$session_token      = null;
		$secure_auth        = false;
		$secure_logged_in   = false;

		$capture_auth             = static function (
			string $cookie,
			int $expire,
			int $expiration,
			int $action_user_id,
			string $scheme,
			string $token
		) use (
			&$captured_auth,
			&$session_token
		): void {
			unset( $expiration, $action_user_id, $scheme );
			$captured_auth = array(
				'cookie' => $cookie,
				'expire' => $expire,
			);
			$session_token = $token;
		};
		$capture_logged_in        = static function (
			string $cookie,
			int $expire
		) use ( &$captured_logged_in ): void {
			$captured_logged_in = array(
				'cookie' => $cookie,
				'expire' => $expire,
			);
		};
		$capture_secure_auth      = static function ( bool $secure ) use ( &$secure_auth ): bool {
			$secure_auth = $secure;

			return $secure;
		};
		$capture_secure_logged_in = static function ( bool $secure ) use ( &$secure_logged_in ): bool {
			$secure_logged_in = $secure;

			return $secure;
		};
		$suppress_core_cookies    = static fn(): bool => false;

		$this->cookies->clear_existing();

		add_action( 'set_auth_cookie', $capture_auth, PHP_INT_MAX, 6 );
		add_action( 'set_logged_in_cookie', $capture_logged_in, PHP_INT_MAX, 2 );
		add_filter( 'secure_auth_cookie', $capture_secure_auth, PHP_INT_MAX, 1 );
		add_filter( 'secure_logged_in_cookie', $capture_secure_logged_in, PHP_INT_MAX, 1 );
		add_filter( 'send_auth_cookies', $suppress_core_cookies, PHP_INT_MAX, 1 );

		try {
			wp_set_auth_cookie( $user_id, false );
		} finally {
			remove_action( 'set_auth_cookie', $capture_auth, PHP_INT_MAX );
			remove_action( 'set_logged_in_cookie', $capture_logged_in, PHP_INT_MAX );
			remove_filter( 'secure_auth_cookie', $capture_secure_auth, PHP_INT_MAX );
			remove_filter( 'secure_logged_in_cookie', $capture_secure_logged_in, PHP_INT_MAX );
			remove_filter( 'send_auth_cookies', $suppress_core_cookies, PHP_INT_MAX );
		}

		if (
			! is_array( $captured_auth )
			|| ! is_array( $captured_logged_in )
			|| ! is_string( $session_token )
			|| '' === $session_token
		) {
			$this->revoke_session( $user_id, $session_token );
			throw new RuntimeException( 'Passwordless session cannot be established.' );
		}

		$auth_name        = (string) constant( $secure_auth ? 'SECURE_AUTH_COOKIE' : 'AUTH_COOKIE' );
		$plugins_path     = (string) constant( 'PLUGINS_COOKIE_PATH' );
		$admin_path       = (string) constant( 'ADMIN_COOKIE_PATH' );
		$logged_in_name   = (string) constant( 'LOGGED_IN_COOKIE' );
		$cookie_path      = (string) constant( 'COOKIEPATH' );
		$site_cookie_path = (string) constant( 'SITECOOKIEPATH' );

		try {
			$this->write_cookie(
				$auth_name,
				$captured_auth['cookie'],
				$captured_auth['expire'],
				$plugins_path,
				$secure_auth
			);
			$this->write_cookie(
				$auth_name,
				$captured_auth['cookie'],
				$captured_auth['expire'],
				$admin_path,
				$secure_auth
			);
			$this->write_cookie(
				$logged_in_name,
				$captured_logged_in['cookie'],
				$captured_logged_in['expire'],
				$cookie_path,
				$secure_logged_in
			);

			if ( $cookie_path !== $site_cookie_path ) {
				$this->write_cookie(
					$logged_in_name,
					$captured_logged_in['cookie'],
					$captured_logged_in['expire'],
					$site_cookie_path,
					$secure_logged_in
				);
			}
		} catch ( Throwable $exception ) {
			$this->cookies->clear_existing();
			$this->revoke_session( $user_id, $session_token );
			throw $exception;
		}

		wp_set_current_user( $user_id );
		do_action( 'wp_login', $user->user_login, $user );
	}

	/**
	 * Revoke a server-side session that could not be delivered to the browser.
	 *
	 * @param int         $user_id Positive WordPress User ID.
	 * @param string|null $token Optional WordPress Session Token.
	 */
	private function revoke_session( int $user_id, ?string $token ): void {
		if ( null === $token || '' === $token ) {
			return;
		}

		\WP_Session_Tokens::get_instance( $user_id )->destroy( $token );
	}

	/**
	 * Send one HttpOnly SameSite=Lax authentication cookie.
	 *
	 * @param string $name Cookie name.
	 * @param string $value WordPress-generated cookie value.
	 * @param int    $expires Expiration timestamp.
	 * @param string $path WordPress-derived cookie path.
	 * @param bool   $secure HTTPS-only decision.
	 * @throws RuntimeException When the browser cookie cannot be written.
	 */
	private function write_cookie(
		string $name,
		string $value,
		int $expires,
		string $path,
		bool $secure
	): void {
		$written = $this->cookies->write(
			$name,
			$value,
			new AuthenticationCookieOptions(
				$expires,
				$path,
				(string) COOKIE_DOMAIN,
				$secure
			)
		);

		if ( ! $written ) {
			throw new RuntimeException( 'Passwordless session cookie could not be written.' );
		}
	}
}
