<?php
/**
 * Native PHP authentication cookie emitter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Auth;

/**
 * Adapts PHP response headers and WordPress cookie clearing.
 */
final class PhpAuthenticationCookieEmitter implements AuthenticationCookieEmitter {
	/**
	 * Determine whether new response headers can still be emitted.
	 */
	public function headers_sent(): bool {
		return headers_sent();
	}

	/**
	 * Expire any browser authentication cookies on the current response.
	 */
	public function clear_existing(): void {
		wp_clear_auth_cookie();
	}

	/**
	 * Emit one authentication cookie.
	 *
	 * @param string                      $name Cookie name.
	 * @param string                      $value WordPress-generated cookie value.
	 * @param AuthenticationCookieOptions $options Cookie options.
	 */
	public function write( string $name, string $value, AuthenticationCookieOptions $options ): bool {
		return setcookie( $name, $value, $options->to_native_options() );
	}
}
