<?php
/**
 * Conversation relay email port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Application\Conversation;

use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;

/** Delivers one privacy-minimized relay email. */
interface ConversationRelayEmailSender {
	/**
	 * Submit one email without cross-party headers.
	 *
	 * @param EmailAddress      $recipient Recipient.
	 * @param MessageSenderRole $recipient_role Recipient role.
	 * @param string|null       $message Optional body.
	 * @param string            $continue_url Secure continuation URL.
	 * @param string            $idempotency_key Opaque stable business key.
	 */
	public function send( EmailAddress $recipient, MessageSenderRole $recipient_role, ?string $message, string $continue_url, string $idempotency_key ): bool;
}
