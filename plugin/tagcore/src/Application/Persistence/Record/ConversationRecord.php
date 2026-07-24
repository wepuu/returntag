<?php
/**
 * Stored finder conversation record.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * One persisted privacy-preserving conversation row.
 */
final readonly class ConversationRecord {
	/**
	 * Create a stored Conversation record.
	 *
	 * @param int                   $conversation_id Conversation identifier.
	 * @param NewConversationRecord $data Stored Conversation data.
	 */
	public function __construct(
		public int $conversation_id,
		public NewConversationRecord $data
	) {
		RecordValidator::positive_id( $this->conversation_id, 'conversation_id' );
	}
}
