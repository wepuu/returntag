<?php
/**
 * Environment-only Resend configuration.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Email;

use RuntimeException;

/** Loads secrets and From identity without WordPress Options. */
final readonly class ResendConfiguration {
	public const API_KEY_NAME        = 'RETURNTAG_TAGCORE_RESEND_API_KEY';
	public const FROM_EMAIL_NAME     = 'RETURNTAG_TAGCORE_RESEND_FROM_EMAIL';
	public const FROM_NAME_NAME      = 'RETURNTAG_TAGCORE_RESEND_FROM_NAME';
	public const WEBHOOK_SECRET_NAME = 'RETURNTAG_TAGCORE_RESEND_WEBHOOK_SECRET';

	/**
	 * Create validated send configuration.
	 *
	 * @param string $api_key Restricted provider credential.
	 * @param string $from_email Approved sender address.
	 * @param string $from_name Approved sender display name.
	 * @throws RuntimeException When configuration is invalid.
	 */
	public function __construct( public string $api_key, public string $from_email, public string $from_name ) {
		if (
			1 !== preg_match( '/^re_[A-Za-z0-9_-]{16,}$/D', $api_key )
			|| ! is_email( $from_email )
			|| '' === trim( $from_name )
			|| strlen( $from_name ) > 100
			|| str_contains( $from_name, "\r" )
			|| str_contains( $from_name, "\n" )
		) {
			throw new RuntimeException( 'Transactional email configuration is unavailable.' );
		}
	}

	/**
	 * Load send configuration from constants or process environment.
	 *
	 * @throws RuntimeException When configuration is unavailable.
	 */
	public static function load(): self {
		return new self(
			self::read( self::API_KEY_NAME ),
			self::read( self::FROM_EMAIL_NAME ),
			self::read( self::FROM_NAME_NAME, 'ForgeTag' )
		);
	}

	/**
	 * Load and validate the signing secret independently from send credentials.
	 *
	 * @throws RuntimeException When configuration is unavailable.
	 */
	public static function webhook_secret(): string {
		$secret = self::read( self::WEBHOOK_SECRET_NAME );
		if ( 1 !== preg_match( '/^whsec_[A-Za-z0-9+\/=_-]{20,}$/D', $secret ) ) {
			throw new RuntimeException( 'Transactional email webhook configuration is unavailable.' );
		}
		return $secret;
	}

	/**
	 * Read one external value without exposing it in an error.
	 *
	 * @param string      $name Constant and environment variable name.
	 * @param string|null $fallback Optional non-secret fallback.
	 * @throws RuntimeException When a required value is unavailable.
	 */
	private static function read( string $name, ?string $fallback = null ): string {
		$value = defined( $name ) ? constant( $name ) : getenv( $name );
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			if ( null !== $fallback ) {
				return $fallback;
			}
			throw new RuntimeException( 'Transactional email configuration is unavailable.' );
		}
		return trim( $value );
	}
}
