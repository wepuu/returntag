<?php
/**
 * OTP password hash persistence value.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Value;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * Rejects plaintext challenge codes and unrecognized password-hash formats.
 */
final readonly class OtpHash {
	/**
	 * Create a recognized password hash.
	 *
	 * @param string $value Hash returned by PHP password hashing.
	 * @throws InvalidArgumentException When the value is not a recognized password hash.
	 */
	private function __construct( public string $value ) {
		RecordValidator::ascii( $this->value, 255, 'code_hash' );
		$information = password_get_info( $this->value );

		if ( 'unknown' === $information['algoName'] ) {
			throw new InvalidArgumentException( 'Persistence value is invalid.' );
		}
	}

	/**
	 * Wrap a hash returned by an approved password-hashing adapter.
	 *
	 * @param string $value Recognized password hash.
	 */
	public static function from_password_hash( string $value ): self {
		return new self( $value );
	}

	/**
	 * Reconstitute a stored OTP password hash.
	 *
	 * @param string $value Stored recognized password hash.
	 */
	public static function from_storage( string $value ): self {
		return new self( $value );
	}
}
