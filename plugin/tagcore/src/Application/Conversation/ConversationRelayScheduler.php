<?php
/**
 * Conversation Message scheduler port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Application\Conversation;

/** Schedules Message-ID-only delivery work. */
interface ConversationRelayScheduler {
	/**
	 * Schedule one durable Message identifier.
	 *
	 * @param int $message_id Message identifier.
	 */
	public function schedule( int $message_id ): void;
}
