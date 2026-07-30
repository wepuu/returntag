<?php
/**
 * Activation OTP dispatch result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

/**
 * Internal non-sensitive Worker outcomes.
 */
enum ActivationOtpDispatchResult: string {
	case ACCEPTED_BY_MAILER = 'accepted_by_mailer';
	case MAILER_REJECTED    = 'mailer_rejected';
	case NO_ACTION          = 'no_action';
}
