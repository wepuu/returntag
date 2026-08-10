<?php
/**
 * Finder Report message key material.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Security;

use RuntimeException;

/** Loads a dedicated external message-encryption key. */
final readonly class FinderReportMessageSecrets {
	public const KEY_NAME = 'RETURNTAG_TAGCORE_FINDER_REPORT_MESSAGE_KEY_V1';

	public const KEY_ID = 'v1';

	/**
	 * Create validated key material.
	 *
	 * @param string $key Binary encryption key.
	 * @throws RuntimeException When key length is invalid.
	 */
	private function __construct( public string $key ) {
		if ( SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES !== strlen( $this->key ) ) {
			throw new RuntimeException( 'Finder Report message security configuration is invalid.' );
		}
	}

	/**
	 * Load the Base64 key from a constant or process environment.
	 *
	 * @throws RuntimeException When configuration is absent or invalid.
	 */
	public static function load(): self {
		$value = defined( self::KEY_NAME ) ? constant( self::KEY_NAME ) : getenv( self::KEY_NAME );

		if ( ! is_string( $value ) || '' === $value ) {
			throw new RuntimeException( 'Finder Report message security configuration is unavailable.' );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Explicit external binary key material.
		$key = base64_decode( $value, true );

		if ( ! is_string( $key ) ) {
			throw new RuntimeException( 'Finder Report message security configuration is invalid.' );
		}

		return new self( $key );
	}

	/**
	 * Build safe injectable key material for tests.
	 *
	 * @param string $key Binary encryption key.
	 */
	public static function from_key( string $key ): self {
		return new self( $key );
	}
}
