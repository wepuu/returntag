<?php
/**
 * Decrypted relay Message view.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Application\Conversation;

use DateTimeImmutable;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;

/** One authorized, decrypted human Message. */
final readonly class ConversationRelayMessage {
	/**
	 * Create one authorized Message view.
	 *
	 * @param int               $message_id Message identifier.
	 * @param MessageSenderRole $sender_role Sender role.
	 * @param string            $body Plaintext body.
	 * @param DateTimeImmutable $created_at Creation time.
	 */
	public function __construct(
		public int $message_id,
		public MessageSenderRole $sender_role,
		public string $body,
		public DateTimeImmutable $created_at
	) {}
}
