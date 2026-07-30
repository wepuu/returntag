<?php
/**
 * Canonical email address value.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\Auth;

use InvalidArgumentException;

/**
 * Validates and normalizes one phase-one email identity.
 */
final readonly class EmailAddress {
	/**
	 * Canonical lowercase email address.
	 *
	 * @var string
	 */
	public string $value;

	/**
	 * Create a canonical email address.
	 *
	 * @param string $value Untrusted email input.
	 * @throws InvalidArgumentException When the email is invalid.
	 */
	public function __construct( string $value ) {
		$value = strtolower( trim( $value ) );

		if (
			'' === $value
			|| strlen( $value ) > 254
			|| 1 !== preg_match( '/^[\x21-\x7e]+$/D', $value )
			|| false === filter_var( $value, FILTER_VALIDATE_EMAIL )
		) {
			throw new InvalidArgumentException( 'Email address is invalid.' );
		}

		$this->value = $value;
	}
}
