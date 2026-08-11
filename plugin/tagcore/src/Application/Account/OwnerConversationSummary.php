<?php
/**
 * Privacy-minimized Owner Conversation summary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Conversation\ConversationStatus;

/** Contains no email, Message, Token, report, evidence, or media value. */
final readonly class OwnerConversationSummary {
	/**
	 * Create one bounded summary.
	 *
	 * @param int                $conversation_id Internal candidate selector.
	 * @param ConversationStatus $status Canonical Conversation status.
	 * @param DateTimeImmutable  $last_activity_at UTC last activity time.
	 * @param DateTimeImmutable  $created_at UTC creation time.
	 * @param bool               $can_continue Whether a continuation action may be shown.
	 */
	public function __construct(
		public int $conversation_id,
		public ConversationStatus $status,
		public DateTimeImmutable $last_activity_at,
		public DateTimeImmutable $created_at,
		public bool $can_continue
	) {
		RecordValidator::positive_id( $conversation_id, 'conversation_id' );
		RecordValidator::utc( $last_activity_at, 'last_activity_at' );
		RecordValidator::utc( $created_at, 'created_at' );
	}
}
