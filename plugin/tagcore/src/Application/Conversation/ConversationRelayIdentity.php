<?php
/**
 * Authorized Conversation relay identity.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Conversation;

use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;

/** One server-resolved Conversation actor. */
final readonly class ConversationRelayIdentity {
	/**
	 * Create an authorized identity.
	 *
	 * @param int               $conversation_id Conversation identifier.
	 * @param MessageSenderRole $role Actor role.
	 * @throws \InvalidArgumentException When the identity is invalid.
	 */
	public function __construct( public int $conversation_id, public MessageSenderRole $role ) {
		if ( $conversation_id < 1 || MessageSenderRole::SYSTEM === $role ) {
			throw new \InvalidArgumentException( 'Conversation identity is invalid.' );
		}
	}
}
