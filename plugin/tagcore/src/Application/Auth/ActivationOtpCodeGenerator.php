<?php
/**
 * Six-digit OTP generator port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

/**
 * Generates one in-memory six-digit code.
 */
interface ActivationOtpCodeGenerator {
	/**
	 * Generate one exact six-digit string.
	 */
	public function generate(): string;
}
