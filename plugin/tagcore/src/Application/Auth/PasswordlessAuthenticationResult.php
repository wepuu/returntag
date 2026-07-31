<?php
/**
 * Passwordless authentication result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

/**
 * Privacy-safe passwordless authentication outcomes.
 */
enum PasswordlessAuthenticationResult: string {
	case AUTHENTICATED         = 'authenticated';
	case ALREADY_AUTHENTICATED = 'already_authenticated';
	case INVALID               = 'invalid';
}
