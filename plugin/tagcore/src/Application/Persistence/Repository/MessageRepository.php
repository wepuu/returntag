<?php
/**
 * Message persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Repository;

use ReturnTag\TagCore\Application\Persistence\Pagination\MessageCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\MessagePage;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Record\MessageRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewMessageRecord;

/**
 * Append-and-query encrypted Message persistence contract.
 */
interface MessageRepository {
	/**
	 * Append one encrypted Message.
	 *
	 * @param NewMessageRecord $record New Message data.
	 */
	public function append( NewMessageRecord $record ): MessageRecord;

	/**
	 * Return one bounded chronological Message page.
	 *
	 * @param int                $conversation_id Conversation identifier.
	 * @param MessageCursor|null $cursor Previous cursor.
	 * @param PageSize           $page_size Bounded page size.
	 */
	public function list_by_conversation( int $conversation_id, ?MessageCursor $cursor, PageSize $page_size ): MessagePage;
}
