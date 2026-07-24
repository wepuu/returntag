<?php
/**
 * Conversation persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Repository;

use ReturnTag\TagCore\Application\Persistence\Record\ConversationRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewConversationRecord;

/**
 * Narrow privacy-preserving Conversation persistence contract.
 */
interface ConversationRepository {
	/**
	 * Insert one privacy-preserving Conversation.
	 *
	 * @param NewConversationRecord $record New Conversation data.
	 */
	public function insert( NewConversationRecord $record ): ConversationRecord;

	/**
	 * Find one Conversation by identifier.
	 *
	 * @param int $conversation_id Conversation identifier.
	 */
	public function find_by_id( int $conversation_id ): ?ConversationRecord;
}
