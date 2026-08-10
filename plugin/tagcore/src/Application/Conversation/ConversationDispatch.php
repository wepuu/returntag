<?php
/**
 * Claimed Conversation Message dispatch context.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Conversation;

use ReturnTag\TagCore\Application\Persistence\Record\ConversationRecord;
use ReturnTag\TagCore\Application\Persistence\Record\MessageRecord;

/** Private Worker-only dispatch projection. */
final readonly class ConversationDispatch {
	/**
	 * Create one dispatch projection.
	 *
	 * @param MessageRecord      $message Message.
	 * @param ConversationRecord $conversation Conversation.
	 * @param int                $finder_report_id Report identifier.
	 * @param int                $current_owner_id Owner identifier.
	 * @throws \InvalidArgumentException When identifiers are invalid.
	 */
	public function __construct(
		public MessageRecord $message,
		public ConversationRecord $conversation,
		public int $finder_report_id,
		public int $current_owner_id
	) {
		if ( $finder_report_id < 1 || $current_owner_id < 1 ) {
			throw new \InvalidArgumentException( 'Conversation dispatch identity is invalid.' );
		}
	}
}
