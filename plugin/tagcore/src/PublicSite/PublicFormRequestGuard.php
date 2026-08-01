<?php
/**
 * Shared public browser-form request guard.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use InvalidArgumentException;

/**
 * Reads bounded request values and rejects browser cross-site evidence.
 */
final class PublicFormRequestGuard {
	/**
	 * Validate one bounded anonymous WordPress nonce.
	 *
	 * @param string $field POST field name.
	 * @param string $action Nonce action.
	 */
	public function valid_nonce( string $field, string $action ): bool {
		$nonce = $this->post_string( $field, 64 );

		return '' !== $nonce && false !== wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Reject browser requests carrying cross-site evidence.
	 */
	public function is_same_site(): bool {
		$fetch_site = $this->server_string( 'HTTP_SEC_FETCH_SITE', 32 );

		if ( '' !== $fetch_site && ! in_array( strtolower( $fetch_site ), array( 'same-origin', 'same-site', 'none' ), true ) ) {
			return false;
		}

		$origin = $this->server_string( 'HTTP_ORIGIN', 512 );

		if ( '' === $origin ) {
			return true;
		}

		$expected = wp_parse_url( home_url( '/' ) );
		$actual   = wp_parse_url( $origin );

		if ( ! is_array( $expected ) || ! is_array( $actual ) ) {
			return false;
		}

		return strtolower( (string) ( $expected['scheme'] ?? '' ) ) === strtolower( (string) ( $actual['scheme'] ?? '' ) )
			&& strtolower( (string) ( $expected['host'] ?? '' ) ) === strtolower( (string) ( $actual['host'] ?? '' ) )
			&& (int) ( $expected['port'] ?? 0 ) === (int) ( $actual['port'] ?? 0 );
	}

	/**
	 * Return only the direct peer address.
	 *
	 * @throws InvalidArgumentException When the direct peer is unavailable.
	 */
	public function direct_peer_ip(): string {
		$ip = $this->server_string( 'REMOTE_ADDR', 64 );

		if ( '' === $ip || false === inet_pton( $ip ) ) {
			throw new InvalidArgumentException( 'Client address is unavailable.' );
		}

		return $ip;
	}

	/**
	 * Read one bounded POST string.
	 *
	 * @param string $key Input key.
	 * @param int    $maximum_bytes Hard byte limit.
	 */
	public function post_string( string $key, int $maximum_bytes ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Callers validate the nonce before business work.
		$value = $_POST[ $key ] ?? '';

		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = wp_unslash( $value );

		return strlen( $value ) <= $maximum_bytes ? $value : '';
	}

	/**
	 * Read one bounded server string.
	 *
	 * @param string $key Server key.
	 * @param int    $maximum_bytes Hard byte limit.
	 */
	private function server_string( string $key, int $maximum_bytes ): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are bounded and parsed against closed policies.
		$value = $_SERVER[ $key ] ?? '';

		return is_string( $value ) && strlen( $value ) <= $maximum_bytes ? $value : '';
	}
}
