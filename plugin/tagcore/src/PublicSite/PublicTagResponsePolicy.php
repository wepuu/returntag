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
	 * @param bool               $activation_post Whether this is the approved activation mutation.
	 */
	public function status_for( string $method, PublicTagPageState $state, bool $activation_post = false ): int {
		$method = strtoupper( $method );

		if ( 'POST' === $method && $activation_post ) {
			return 200;
		}

		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
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
	 * @param bool   $activation_post Whether this is the approved activation mutation.
	 * @return array<string, string>
	 */
	public function headers_for_method( string $method, bool $activation_post = false ): array {
		$headers = array(
			'Cache-Control'           => 'no-store, private',
			'Pragma'                  => 'no-cache',
			'Referrer-Policy'         => 'no-referrer',
			'X-Content-Type-Options'  => 'nosniff',
			'X-Robots-Tag'            => 'noindex, nofollow, noarchive',
			'Content-Security-Policy' => "default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self' data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
		);

		if ( ! in_array( strtoupper( $method ), array( 'GET', 'HEAD' ), true ) && ! $activation_post ) {
			$headers['Allow'] = 'GET, HEAD, POST';
		}

		return $headers;
	}
}
