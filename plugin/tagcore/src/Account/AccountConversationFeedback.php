<?php
/**
 * Privacy-safe Account Conversation feedback.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

/** Exposes no Conversation existence or authorization detail. */
enum AccountConversationFeedback: string {
	case NONE        = 'none';
	case UNAVAILABLE = 'unavailable';
}
