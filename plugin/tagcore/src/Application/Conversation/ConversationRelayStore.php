<?php
/**
 * Atomic Conversation relay persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Conversation;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Record\AccessTokenRecord;
use ReturnTag\TagCore\Application\Persistence\Record\MessageRecord;
use ReturnTag\TagCore\Application\Persistence\Value\AccessTokenDigest;
use ReturnTag\TagCore\Application\Persistence\Value\MessageCiphertext;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;

/** Owns the transactional token, session, Message-limit, and dispatch boundary. */
interface ConversationRelayStore {
	/**
	 * Resolve a notified report.
	 *
	 * @param int               $finder_report_id Report ID.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function conversation_id_for_notified_report( int $finder_report_id, DateTimeImmutable $now ): ?int;
	/**
	 * Create an access Message.
	 *
	 * @param int               $finder_report_id Report ID.
	 * @param MessageCiphertext $ciphertext Body.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function ensure_access_message( int $finder_report_id, MessageCiphertext $ciphertext, DateTimeImmutable $now ): ?MessageRecord;

	/**
	 * Persist one link.
	 *
	 * @param int               $conversation_id Conversation ID.
	 * @param string            $purpose Purpose.
	 * @param MessageSenderRole $role Role.
	 * @param AccessTokenDigest $digest Digest.
	 * @param DateTimeImmutable $expires_at Expiry.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function issue_link( int $conversation_id, string $purpose, MessageSenderRole $role, AccessTokenDigest $digest, DateTimeImmutable $expires_at, DateTimeImmutable $now ): AccessTokenRecord;

	/**
	 * Exchange one link.
	 *
	 * @param AccessTokenDigest $link Link.
	 * @param AccessTokenDigest $session Session.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param DateTimeImmutable $session_expires_at Expiry.
	 */
	public function exchange_link( AccessTokenDigest $link, AccessTokenDigest $session, DateTimeImmutable $now, DateTimeImmutable $session_expires_at ): ?ConversationRelayIdentity;

	/**
	 * Resolve one session.
	 *
	 * @param AccessTokenDigest $session Session.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function resolve_session( AccessTokenDigest $session, DateTimeImmutable $now ): ?ConversationRelayIdentity;

	/**
	 * Append one Message.
	 *
	 * @param ConversationRelayIdentity $identity Actor.
	 * @param MessageCiphertext         $ciphertext Body.
	 * @param DateTimeImmutable         $now Current UTC time.
	 */
	public function append_human_message( ConversationRelayIdentity $identity, MessageCiphertext $ciphertext, DateTimeImmutable $now ): ?MessageRecord;

	/**
	 * List human Messages.
	 *
	 * @param ConversationRelayIdentity $identity Actor.
	 * @param DateTimeImmutable         $now Current UTC time.
	 * @return list<MessageRecord>
	 */
	public function list_human_messages( ConversationRelayIdentity $identity, DateTimeImmutable $now ): array;

	/**
	 * Apply one atomic role-specific terminal action.
	 *
	 * @param ConversationRelayIdentity $identity Actor.
	 * @param ConversationSafetyAction  $action Terminal action.
	 * @param DateTimeImmutable         $now Current UTC time.
	 */
	public function apply_safety_action( ConversationRelayIdentity $identity, ConversationSafetyAction $action, DateTimeImmutable $now ): bool;

	/**
	 * Claim one dispatch.
	 *
	 * @param int               $message_id Message ID.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function claim_dispatch( int $message_id, DateTimeImmutable $now ): ?ConversationDispatch;

	/**
	 * Recheck one claimed dispatch and its exact continuation Token.
	 *
	 * @param int               $message_id Message ID.
	 * @param int               $token_id Continuation Token ID.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function dispatch_is_active( int $message_id, int $token_id, DateTimeImmutable $now ): bool;

	/**
	 * Mark provider acceptance.
	 *
	 * @param int               $message_id Message ID.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_sent( int $message_id, DateTimeImmutable $now ): bool;

	/**
	 * Mark terminal failure.
	 *
	 * @param int               $message_id Message ID.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_failed( int $message_id, DateTimeImmutable $now ): bool;

	/**
	 * Revoke one Token.
	 *
	 * @param int               $token_id Token ID.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function revoke_token( int $token_id, DateTimeImmutable $now ): void;

	/**
	 * Fail stale claims.
	 *
	 * @param DateTimeImmutable $before Boundary.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param int               $limit Bound.
	 */
	public function fail_stale_claims( DateTimeImmutable $before, DateTimeImmutable $now, int $limit ): int;

	/**
	 * List pending Message identifiers.
	 *
	 * @param int $limit Bound.
	 * @return list<int>
	 */
	public function pending_message_ids( int $limit ): array;
}
