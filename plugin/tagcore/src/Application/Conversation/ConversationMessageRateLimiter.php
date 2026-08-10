<?php
/**
 * Conversation Message abuse limiter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Application\Conversation;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
interface ConversationMessageRateLimiter {
	/**
	 * Reserve keyed request budgets.
	 *
	 * @param LookupDigest      $session Session lookup.
	 * @param LookupDigest      $peer Peer lookup.
	 * @param int               $conversation_id Conversation identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve( LookupDigest $session, LookupDigest $peer, int $conversation_id, DateTimeImmutable $now ): bool;
}
