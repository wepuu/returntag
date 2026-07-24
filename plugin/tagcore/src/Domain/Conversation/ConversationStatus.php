<?php
/**
 * Finder conversation lifecycle values.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\Conversation;

/**
 * Canonical persisted conversation states.
 */
enum ConversationStatus: string {
	case PENDING_VERIFICATION = 'pending_verification';
	case OPEN                 = 'open';
	case CLOSED               = 'closed';
	case BLOCKED              = 'blocked';
	case EXPIRED              = 'expired';
}
