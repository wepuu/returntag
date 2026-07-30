<?php
/**
 * Activation OTP request result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

/**
 * Keeps public feedback privacy-safe.
 */
enum ActivationOtpRequestResult: string {
	case ACCEPTED    = 'accepted';
	case THROTTLED   = 'throttled';
	case UNAVAILABLE = 'unavailable';
}
