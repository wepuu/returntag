<?php
/**
 * Activation OTP secret loader.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Security;

use RuntimeException;

/**
 * Loads three independent versioned keys from wp-config or environment.
 */
final readonly class ActivationOtpSecrets {
	public const EMAIL_KEY_NAME = 'RETURNTAG_TAGCORE_EMAIL_ENCRYPTION_KEY_V1';

	public const LOOKUP_KEY_NAME = 'RETURNTAG_TAGCORE_LOOKUP_HMAC_KEY_V1';

	public const OTP_KEY_NAME = 'RETURNTAG_TAGCORE_OTP_PEPPER_V1';

	/**
	 * Create a validated independent key set.
	 *
	 * @param string $email_key Email-encryption key.
	 * @param string $lookup_key Lookup-HMAC key.
	 * @param string $otp_pepper OTP pepper.
	 */
	private function __construct(
		public string $email_key,
		public string $lookup_key,
		public string $otp_pepper
	) {
	}

	/**
	 * Load exact 32-byte Base64 keys without storing them in WordPress.
	 *
	 * @throws RuntimeException When any key is absent or malformed.
	 */
	public static function load(): self {
		return self::from_keys(
			self::read_key( self::EMAIL_KEY_NAME ),
			self::read_key( self::LOOKUP_KEY_NAME ),
			self::read_key( self::OTP_KEY_NAME )
		);
	}

	/**
	 * Build an injectable key set for composition and safe fixtures.
	 *
	 * @param string $email_key Email-encryption key.
	 * @param string $lookup_key Lookup-HMAC key.
	 * @param string $otp_pepper OTP pepper.
	 * @throws RuntimeException When a key length is invalid.
	 */
	public static function from_keys( string $email_key, string $lookup_key, string $otp_pepper ): self {
		foreach ( array( $email_key, $lookup_key, $otp_pepper ) as $key ) {
			if ( 32 !== strlen( $key ) ) {
				throw new RuntimeException( 'Activation OTP security configuration is invalid.' );
			}
		}

		return new self( $email_key, $lookup_key, $otp_pepper );
	}

	/**
	 * Read a constant first, then the matching process environment value.
	 *
	 * @param string $name Approved constant and environment name.
	 * @throws RuntimeException When the key is missing or invalid.
	 */
	private static function read_key( string $name ): string {
		$value = defined( $name ) ? constant( $name ) : getenv( $name );

		if ( ! is_string( $value ) || '' === $value ) {
			throw new RuntimeException( 'Activation OTP security configuration is unavailable.' );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes explicit external binary key material, not executable content.
		$decoded = base64_decode( $value, true );

		if ( ! is_string( $decoded ) || 32 !== strlen( $decoded ) ) {
			throw new RuntimeException( 'Activation OTP security configuration is invalid.' );
		}

		return $decoded;
	}
}
