<?php
/**
 * Activation OTP verification result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

/**
 * Privacy-safe verification outcomes.
 */
enum ActivationOtpVerificationResult: string {
	case VERIFIED    = 'verified';
	case INVALID     = 'invalid';
	case THROTTLED   = 'throttled';
	case UNAVAILABLE = 'unavailable';
}
