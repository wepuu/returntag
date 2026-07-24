<?php
/**
 * New finder conversation data.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Domain\Conversation\ConversationStatus;

/**
 * Immutable privacy-preserving conversation persistence data.
 */
final readonly class NewConversationRecord {
	/**
	 * Create conversation persistence data.
	 *
	 * @param string                 $tag_id Public Tag ID.
	 * @param int                    $owner_id_snapshot Owner snapshot.
	 * @param EmailCiphertext        $finder_email_ciphertext Opaque encrypted finder email.
	 * @param LookupDigest           $finder_email_lookup Keyed finder lookup digest.
	 * @param DateTimeImmutable|null $finder_verified_at UTC finder verification time.
	 * @param ConversationStatus     $conversation_status Persisted Conversation state.
	 * @param DateTimeImmutable      $expires_at UTC expiry.
	 * @param DateTimeImmutable      $last_activity_at UTC last activity.
	 * @param DateTimeImmutable      $created_at UTC creation time.
	 */
	public function __construct(
		public string $tag_id,
		public int $owner_id_snapshot,
		public EmailCiphertext $finder_email_ciphertext,
		public LookupDigest $finder_email_lookup,
		public ?DateTimeImmutable $finder_verified_at,
		public ConversationStatus $conversation_status,
		public DateTimeImmutable $expires_at,
		public DateTimeImmutable $last_activity_at,
		public DateTimeImmutable $created_at
	) {
		RecordValidator::tag_id( $this->tag_id );
		RecordValidator::positive_id( $this->owner_id_snapshot, 'owner_id_snapshot' );
		RecordValidator::nullable_utc( $this->finder_verified_at, 'finder_verified_at' );
		RecordValidator::utc( $this->expires_at, 'expires_at' );
		RecordValidator::utc( $this->last_activity_at, 'last_activity_at' );
		RecordValidator::utc( $this->created_at, 'created_at' );
	}
}
