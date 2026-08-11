<?php
/**
 * Current-Owner Conversation projection port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use DateTimeImmutable;

/** Reads no encrypted identifier, Message, Token, evidence, or media value. */
interface OwnerConversationReader {
	/**
	 * Return a bounded current-Owner summary list.
	 *
	 * @param int               $owner_id Current WordPress Owner identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @return list<OwnerConversationSummary>
	 */
	public function list_for_owner( int $owner_id, DateTimeImmutable $now ): array;
}
