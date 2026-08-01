<?php
/**
 * Manual Tag entry response policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

/**
 * Defines privacy-safe HTTP semantics for manual entry.
 */
final class ManualTagEntryResponsePolicy {
	/**
	 * Return the status for one method and safe form state.
	 *
	 * @param string                  $method HTTP request method.
	 * @param ManualTagEntryFormState $state Safe presentation state.
	 */
	public function status_for( string $method, ManualTagEntryFormState $state ): int {
		if ( ! in_array( strtoupper( $method ), array( 'GET', 'HEAD', 'POST' ), true ) ) {
			return 405;
		}

		return match ( $state ) {
			ManualTagEntryFormState::READY => 200,
			ManualTagEntryFormState::INVALID => 422,
			ManualTagEntryFormState::FORBIDDEN => 403,
			ManualTagEntryFormState::THROTTLED => 429,
			ManualTagEntryFormState::UNAVAILABLE => 503,
		};
	}

	/**
	 * Return privacy and abuse-control response headers.
	 *
	 * @param string                  $method HTTP request method.
	 * @param ManualTagEntryFormState $state Safe presentation state.
	 *
	 * @return array<string, string>
	 */
	public function headers_for( string $method, ManualTagEntryFormState $state ): array {
		$headers = array(
			'Cache-Control'           => 'no-store, private',
			'Pragma'                  => 'no-cache',
			'Referrer-Policy'         => 'no-referrer',
			'X-Content-Type-Options'  => 'nosniff',
			'X-Robots-Tag'            => 'noindex, nofollow, noarchive',
			'Content-Security-Policy' => "default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self' data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
		);

		if ( ! in_array( strtoupper( $method ), array( 'GET', 'HEAD', 'POST' ), true ) ) {
			$headers['Allow'] = 'GET, HEAD, POST';
		}

		if ( ManualTagEntryFormState::THROTTLED === $state ) {
			$headers['Retry-After'] = '60';
		}

		return $headers;
	}
}
