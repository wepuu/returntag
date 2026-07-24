<?php
/**
 * Conversation message sender roles.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\Conversation;

/**
 * Canonical persisted message sender roles.
 */
enum MessageSenderRole: string {
	case FINDER = 'finder';
	case OWNER  = 'owner';
	case SYSTEM = 'system';
}
