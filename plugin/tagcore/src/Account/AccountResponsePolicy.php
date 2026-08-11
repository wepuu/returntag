<?php
/**
 * Owner Account HTTP policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

/**
 * Defines privacy-safe Account response headers.
 */
final class AccountResponsePolicy {
	/**
	 * Return privacy, indexing, framing, and method controls.
	 *
	 * @param string $method Bounded uppercase request method.
	 * @return array<string, string>
	 */
	public function headers( string $method ): array {
		$headers = array(
			'Cache-Control'           => 'no-store, private',
			'Pragma'                  => 'no-cache',
			'Referrer-Policy'         => 'no-referrer',
			'X-Content-Type-Options'  => 'nosniff',
			'X-Robots-Tag'            => 'noindex, nofollow, noarchive',
			'Content-Security-Policy' => "default-src 'none'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
		);

		if ( ! in_array( strtoupper( $method ), array( 'GET', 'HEAD', 'POST' ), true ) ) {
			$headers['Allow'] = 'GET, HEAD, POST';
		}

		return $headers;
	}
}
