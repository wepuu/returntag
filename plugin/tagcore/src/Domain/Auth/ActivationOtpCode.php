<?php
/**
 * Activation OTP code value.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\Auth;

use InvalidArgumentException;

/**
 * Represents one exact six-digit activation verification code.
 */
final readonly class ActivationOtpCode {
	/**
	 * Create a validated code.
	 *
	 * @param string $value Untrusted code input.
	 * @throws InvalidArgumentException When the code is not exactly six ASCII digits.
	 */
	public function __construct( public string $value ) {
		if ( 1 !== preg_match( '/^[0-9]{6}$/D', $this->value ) ) {
			throw new InvalidArgumentException( 'Activation OTP code is invalid.' );
		}
	}
}
