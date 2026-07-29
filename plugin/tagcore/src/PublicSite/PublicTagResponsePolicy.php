<?php
/**
 * Public Tag route response policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

/**
 * Defines the fail-closed RT-301 HTTP response without reading Tag data.
 */
final class PublicTagResponsePolicy {
	/**
	 * Return the response status for one request method.
	 *
	 * @param string $method HTTP request method.
	 */
	public function status_for_method( string $method ): int {
		return in_array( strtoupper( $method ), array( 'GET', 'HEAD' ), true ) ? 503 : 405;
	}

	/**
	 * Return the privacy and indexing controls for the public route.
	 *
	 * @param string $method HTTP request method.
	 * @return array<string, string>
	 */
	public function headers_for_method( string $method ): array {
		$headers = array(
			'Cache-Control'          => 'no-store, private',
			'Pragma'                 => 'no-cache',
			'Referrer-Policy'        => 'no-referrer',
			'X-Content-Type-Options' => 'nosniff',
			'X-Robots-Tag'           => 'noindex, nofollow, noarchive',
		);

		if ( 405 === $this->status_for_method( $method ) ) {
			$headers['Allow'] = 'GET, HEAD';
		}

		return $headers;
	}
}
