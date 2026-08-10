<?php
/**
 * Finder email verification secret loader.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Security;

use RuntimeException;

/** Loads independent Finder email verification keys from external configuration. */
final readonly class FinderEmailVerificationSecrets {
	public const EMAIL_KEY_NAME  = 'RETURNTAG_TAGCORE_FINDER_EMAIL_ENCRYPTION_KEY_V1';
	public const LOOKUP_KEY_NAME = 'RETURNTAG_TAGCORE_FINDER_EMAIL_LOOKUP_KEY_V1';
	public const OTP_KEY_NAME    = 'RETURNTAG_TAGCORE_FINDER_EMAIL_OTP_PEPPER_V1';

	/**
	 * Create a validated key set.
	 *
	 * @param string $email_key Email encryption key.
	 * @param string $lookup_key Lookup HMAC key.
	 * @param string $otp_pepper OTP pepper.
	 */
	private function __construct( public string $email_key, public string $lookup_key, public string $otp_pepper ) {
	}

	/** Load three external Base64 keys. */
	public static function load(): self {
		return self::from_keys( self::read( self::EMAIL_KEY_NAME ), self::read( self::LOOKUP_KEY_NAME ), self::read( self::OTP_KEY_NAME ) );
	}

	/**
	 * Build an injectable key set.
	 *
	 * @param string $email_key Email encryption key.
	 * @param string $lookup_key Lookup HMAC key.
	 * @param string $otp_pepper OTP pepper.
	 * @throws RuntimeException When a key length is invalid.
	 */
	public static function from_keys( string $email_key, string $lookup_key, string $otp_pepper ): self {
		foreach ( array( $email_key, $lookup_key, $otp_pepper ) as $key ) {
			if ( 32 !== strlen( $key ) ) {
				throw new RuntimeException( 'Finder email verification security configuration is invalid.' );
			}
		}
		return new self( $email_key, $lookup_key, $otp_pepper );
	}

	/**
	 * Read one external key.
	 *
	 * @param string $name Approved external configuration name.
	 * @throws RuntimeException When key material is absent or malformed.
	 */
	private static function read( string $name ): string {
		$value = defined( $name ) ? constant( $name ) : getenv( $name );
		if ( ! is_string( $value ) || '' === $value ) {
			throw new RuntimeException( 'Finder email verification security configuration is unavailable.' );
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- External binary key material.
		$decoded = base64_decode( $value, true );
		if ( ! is_string( $decoded ) || 32 !== strlen( $decoded ) ) {
			throw new RuntimeException( 'Finder email verification security configuration is invalid.' );
		}
		return $decoded;
	}
}
