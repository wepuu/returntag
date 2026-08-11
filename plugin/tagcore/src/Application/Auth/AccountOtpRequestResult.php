<?php
/**
 * Owner Account OTP request outcome.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

enum AccountOtpRequestResult: string {
	case ACCEPTED    = 'accepted';
	case THROTTLED   = 'throttled';
	case UNAVAILABLE = 'unavailable';
}
