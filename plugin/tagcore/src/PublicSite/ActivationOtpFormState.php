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
	case READY         = 'ready';
	case ACCEPTED      = 'accepted';
	case INVALID_EMAIL = 'invalid_email';
	case ERROR         = 'error';
}
