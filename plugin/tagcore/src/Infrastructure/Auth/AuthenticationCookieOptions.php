<?php
/**
 * Authentication cookie options.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Auth;

/**
 * Carries the fixed security attributes for one WordPress authentication cookie.
 */
final readonly class AuthenticationCookieOptions {
	/**
	 * Create one secure cookie option set.
	 *
	 * @param int    $expires Expiration timestamp.
	 * @param string $path WordPress-derived cookie path.
	 * @param string $domain WordPress-derived cookie domain.
	 * @param bool   $secure HTTPS-only decision.
	 */
	public function __construct(
		public int $expires,
		public string $path,
		public string $domain,
		public bool $secure
	) {
	}

	/**
	 * Return the exact native PHP cookie options.
	 *
	 * @return array{
	 *     expires: int,
	 *     path: string,
	 *     domain: string,
	 *     secure: bool,
	 *     httponly: true,
	 *     samesite: 'Lax'
	 * }
	 */
	public function to_native_options(): array {
		return array(
			'expires'  => $this->expires,
			'path'     => $this->path,
			'domain'   => $this->domain,
			'secure'   => $this->secure,
			'httponly' => true,
			'samesite' => 'Lax',
		);
	}
}
