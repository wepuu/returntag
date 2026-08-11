<?php
/**
 * Validated Owner Lost Mode input.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use InvalidArgumentException;

/** Holds canonical Lost Mode and approved Finder-visible guidance. */
final readonly class OwnerTagLostState {
	/**
	 * Optional Finder-visible Lost Message.
	 *
	 * @var string|null
	 */
	public ?string $message;

	/**
	 * Validate complete Lost Mode input.
	 *
	 * @param bool   $enabled Canonical Lost Mode value.
	 * @param string $message Submitted optional Finder guidance.
	 * @throws InvalidArgumentException When the message violates the contract.
	 */
	public function __construct(
		public bool $enabled,
		string $message
	) {
		$message = trim( $message );

		if ( '' === $message ) {
			$this->message = null;

			return;
		}

		if (
			mb_strlen( $message, 'UTF-8' ) > 500
			|| 1 !== preg_match( '//u', $message )
			|| 1 === preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $message )
			|| 1 === preg_match( '/<\s*\/?\s*[a-z][^>]*>/iu', $message )
			|| self::contains_high_risk_content( $message )
		) {
			throw new InvalidArgumentException( 'Lost Message is invalid.' );
		}

		$this->message = $message;
	}

	/**
	 * Reject approved credential, financial, identity, and home-address classes.
	 *
	 * @param string $message Candidate Lost Message.
	 */
	private static function contains_high_risk_content( string $message ): bool {
		$patterns = array(
			'/(?:password|passcode|\bpin\b|one[ -]?time (?:code|password)|verification code|security code|\botp\b)/iu',
			'/(?:bank account|routing number|credit card|debit card|account number|\biban\b|\bswift\b)/iu',
			"/(?:social security|\\bssn\\b|passport number|driver(?:'|’)?s? licen[cs]e|national id|identity document)/iu",
			'/\b\d{8,19}\b/u',
			'/\b\d{1,6}\s+[\p{L}\p{N}.\'-]+(?:\s+[\p{L}\p{N}.\'-]+){0,5}\s+(?:street|st|road|rd|avenue|ave|lane|ln|drive|dr|boulevard|blvd|court|ct|place|pl|terrace|highway|hwy)\b/iu',
			'/(?:apartment|\bapt\b|suite|unit)\s*#?\s*[\p{L}\p{N}-]+/iu',
		);

		foreach ( $patterns as $pattern ) {
			if ( 1 === preg_match( $pattern, $message ) ) {
				return true;
			}
		}

		return false;
	}
}
