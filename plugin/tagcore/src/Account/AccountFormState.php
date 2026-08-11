<?php
/**
 * Owner Account sign-in form states.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

enum AccountFormState: string {
	case READY                = 'ready';
	case CODE_SENT            = 'code_sent';
	case INVALID_EMAIL        = 'invalid_email';
	case VERIFICATION_INVALID = 'verification_invalid';
	case AUTHENTICATED        = 'authenticated';
	case UNAVAILABLE          = 'unavailable';
}
