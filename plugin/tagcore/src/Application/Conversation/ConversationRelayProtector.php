<?php
/**
 * Conversation relay cryptography port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Conversation;

use ReturnTag\TagCore\Application\Persistence\Value\AccessTokenDigest;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\MessageCiphertext;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;

/** Protects relay Tokens, peer identifiers, and Message bodies. */
interface ConversationRelayProtector {
	/** Generate one 32-byte URL-safe bearer Token. */
	public function generate_token(): string;

	/**
	 * Hash one raw bearer Token for persistence.
	 *
	 * @param string $token Raw Token.
	 */
	public function token_digest( string $token ): AccessTokenDigest;

	/**
	 * Create a keyed direct-peer lookup.
	 *
	 * @param string $ip_address Direct peer.
	 */
	public function peer_digest( string $ip_address ): LookupDigest;

	/**
	 * Encrypt one Conversation-bound Message.
	 *
	 * @param string            $message Plaintext.
	 * @param int               $conversation_id Conversation identifier.
	 * @param MessageSenderRole $role Sender role.
	 */
	public function encrypt_message( string $message, int $conversation_id, MessageSenderRole $role ): MessageCiphertext;

	/**
	 * Decrypt one Conversation-bound Message.
	 *
	 * @param MessageCiphertext $ciphertext Encrypted body.
	 * @param int               $conversation_id Conversation identifier.
	 * @param MessageSenderRole $role Sender role.
	 */
	public function decrypt_message( MessageCiphertext $ciphertext, int $conversation_id, MessageSenderRole $role ): string;
}
