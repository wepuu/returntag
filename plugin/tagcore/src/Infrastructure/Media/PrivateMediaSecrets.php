<?php
/**
 * Private-media key material.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Media;

use RuntimeException;

/**
 * Loads independent object and reference keys outside WordPress storage.
 */
final readonly class PrivateMediaSecrets {
	public const OBJECT_KEY_NAME = 'RETURNTAG_TAGCORE_PRIVATE_MEDIA_OBJECT_KEY_V1';

	public const REFERENCE_KEY_NAME = 'RETURNTAG_TAGCORE_PRIVATE_MEDIA_REFERENCE_KEY_V1';

	public const KEY_ID = 'v1';

	/**
	 * Create validated independent key material.
	 *
	 * @param string $object_key Object-encryption key.
	 * @param string $reference_key Reference-encryption key.
	 * @throws RuntimeException When keys are invalid or reused.
	 */
	private function __construct(
		public string $object_key,
		public string $reference_key
	) {
		if (
			SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES !== strlen( $this->object_key )
			|| SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES !== strlen( $this->reference_key )
			|| hash_equals( $this->object_key, $this->reference_key )
		) {
			throw new RuntimeException( 'Private-media security configuration is invalid.' );
		}
	}

	/**
	 * Load Base64 keys from constants or the process environment.
	 */
	public static function load(): self {
		return self::from_keys(
			self::read_key( self::OBJECT_KEY_NAME ),
			self::read_key( self::REFERENCE_KEY_NAME )
		);
	}

	/**
	 * Build injectable key material for composition and safe fixtures.
	 *
	 * @param string $object_key Object-encryption key.
	 * @param string $reference_key Reference-encryption key.
	 */
	public static function from_keys( string $object_key, string $reference_key ): self {
		return new self( $object_key, $reference_key );
	}

	/**
	 * Read and decode one external key.
	 *
	 * @param string $name Approved constant and environment name.
	 * @throws RuntimeException When a key is missing or malformed.
	 */
	private static function read_key( string $name ): string {
		$value = defined( $name ) ? constant( $name ) : getenv( $name );

		if ( ! is_string( $value ) || '' === $value ) {
			throw new RuntimeException( 'Private-media security configuration is unavailable.' );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes explicit external binary key material.
		$decoded = base64_decode( $value, true );

		if ( ! is_string( $decoded ) ) {
			throw new RuntimeException( 'Private-media security configuration is invalid.' );
		}

		return $decoded;
	}
}
