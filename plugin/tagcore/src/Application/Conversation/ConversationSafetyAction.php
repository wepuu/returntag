<?php
/**
 * Role-specific participant Conversation safety actions.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Conversation;

use ReturnTag\TagCore\Domain\Conversation\ConversationStatus;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;

/** Maps the closed browser intent to its frozen role, status, and Event. */
enum ConversationSafetyAction: string {
	case FINDER_CLOSE       = 'finder_close';
	case OWNER_REPORT_BLOCK = 'owner_report_block';

	/** Return the only role allowed to perform this action. */
	public function role(): MessageSenderRole {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- Backed enum instance method on supported PHP.
		return match ( $this ) {
			self::FINDER_CLOSE => MessageSenderRole::FINDER,
			self::OWNER_REPORT_BLOCK => MessageSenderRole::OWNER,
		};
	}

	/** Return the canonical terminal Conversation status. */
	public function status(): ConversationStatus {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- Backed enum instance method on supported PHP.
		return match ( $this ) {
			self::FINDER_CLOSE => ConversationStatus::CLOSED,
			self::OWNER_REPORT_BLOCK => ConversationStatus::BLOCKED,
		};
	}

	/** Return the metadata-free audit Event type. */
	public function event_type(): string {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- Backed enum instance method on supported PHP.
		return match ( $this ) {
			self::FINDER_CLOSE => 'conversation_closed',
			self::OWNER_REPORT_BLOCK => 'conversation_reported',
		};
	}
}
