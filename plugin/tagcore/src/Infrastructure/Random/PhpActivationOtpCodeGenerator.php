<?php
/**
 * Cryptographically secure activation OTP generator.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Random;

use ReturnTag\TagCore\Application\Auth\ActivationOtpCodeGenerator;

/**
 * Generates an exact six-digit string in memory.
 */
final class PhpActivationOtpCodeGenerator implements ActivationOtpCodeGenerator {
	/**
	 * Generate one cryptographically random six-digit string.
	 */
	public function generate(): string {
		return str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
	}
}
