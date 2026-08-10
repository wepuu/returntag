<?php
/**
 * Conversation Owner recipient port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Application\Conversation;

use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/** Resolves a validated current Owner to a private email destination. */
interface ConversationRelayOwnerResolver {
	/**
	 * Resolve one internal WordPress user identifier.
	 *
	 * @param int $owner_id Owner identifier.
	 */
	public function resolve( int $owner_id ): ?EmailAddress;
}
