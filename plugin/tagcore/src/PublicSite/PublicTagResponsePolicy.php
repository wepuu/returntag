<?php
/**
 * Public Tag route response policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use ReturnTag\TagCore\Application\PublicTag\PublicTagPageState;

/**
 * Defines privacy-safe HTTP semantics for one derived public page.
 */
final class PublicTagResponsePolicy {
	/**
	 * Return the response status for one request method and page state.
	 *
	 * @param string             $method HTTP request method.
	 * @param PublicTagPageState $state Derived public page state.
	 */
	public function status_for( string $method, PublicTagPageState $state ): int {
		if ( ! in_array( strtoupper( $method ), array( 'GET', 'HEAD' ), true ) ) {
			return 405;
		}

		return match ( $state ) {
			PublicTagPageState::INVALID => 404,
			PublicTagPageState::SERVICE_UNAVAILABLE => 503,
			default => 200,
		};
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

		if ( ! in_array( strtoupper( $method ), array( 'GET', 'HEAD' ), true ) ) {
			$headers['Allow'] = 'GET, HEAD';
		}

		return $headers;
	}
}
