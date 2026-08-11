<?php
/**
 * Owner Conversation projection state.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

/** Keeps authentication and generic availability outcomes closed. */
enum OwnerConversationAccessState: string {
	case READY                   = 'ready';
	case AUTHENTICATION_REQUIRED = 'authentication_required';
	case UNAVAILABLE             = 'unavailable';
}
