<?php
/**
 * Authentication cookie emission boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Auth;

/**
 * Isolates native browser header effects from session-token orchestration.
 */
interface AuthenticationCookieEmitter {
	/**
	 * Determine whether new response headers can still be emitted.
	 */
	public function headers_sent(): bool;

	/**
	 * Expire any browser authentication cookies on the current response.
	 */
	public function clear_existing(): void;

	/**
	 * Emit one authentication cookie.
	 *
	 * @param string                      $name Cookie name.
	 * @param string                      $value WordPress-generated cookie value.
	 * @param AuthenticationCookieOptions $options Cookie options.
	 */
	public function write( string $name, string $value, AuthenticationCookieOptions $options ): bool;
}
