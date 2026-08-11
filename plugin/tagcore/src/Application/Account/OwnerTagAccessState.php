<?php
/**
 * Closed Owner Account read states.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

/**
 * Prevents persistence or authorization detail from reaching templates.
 */
enum OwnerTagAccessState: string {
	case READY                   = 'ready';
	case AUTHENTICATION_REQUIRED = 'authentication_required';
	case UNAVAILABLE             = 'unavailable';
}
