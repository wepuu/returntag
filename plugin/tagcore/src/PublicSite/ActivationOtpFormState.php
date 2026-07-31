<?php
/**
 * Public activation OTP form state.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

/**
 * Presentation-safe form feedback.
 */
enum ActivationOtpFormState: string {
	case READY                 = 'ready';
	case REQUEST_ACCEPTED      = 'request_accepted';
	case REQUEST_INVALID_EMAIL = 'request_invalid_email';
	case REQUEST_ERROR         = 'request_error';
	case VERIFICATION_INVALID  = 'verification_invalid';
	case AUTHENTICATED         = 'authenticated';
}
