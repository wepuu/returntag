<?php
/**
 * New hash-only Access Token data.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Value\AccessTokenDigest;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;

/**
 * Immutable hash-only Access Token persistence data.
 */
final readonly class NewAccessTokenRecord {
	/**
	 * Create Access Token persistence data.
	 *
	 * @param int                    $conversation_id Conversation identifier.
	 * @param string                 $purpose Token purpose.
	 * @param MessageSenderRole      $actor_role Bound actor role.
	 * @param AccessTokenDigest      $token_hash Canonical Token digest.
	 * @param DateTimeImmutable      $expires_at UTC expiry.
	 * @param DateTimeImmutable|null $exchanged_at UTC exchange time.
	 * @param DateTimeImmutable|null $revoked_at UTC revocation time.
	 * @param DateTimeImmutable      $created_at UTC creation time.
	 */
	public function __construct(
		public int $conversation_id,
		public string $purpose,
		public MessageSenderRole $actor_role,
		public AccessTokenDigest $token_hash,
		public DateTimeImmutable $expires_at,
		public ?DateTimeImmutable $exchanged_at,
		public ?DateTimeImmutable $revoked_at,
		public DateTimeImmutable $created_at
	) {
		RecordValidator::positive_id( $this->conversation_id, 'conversation_id' );
		RecordValidator::ascii( $this->purpose, 32, 'purpose' );
		RecordValidator::utc( $this->expires_at, 'expires_at' );
		RecordValidator::nullable_utc( $this->exchanged_at, 'exchanged_at' );
		RecordValidator::nullable_utc( $this->revoked_at, 'revoked_at' );
		RecordValidator::utc( $this->created_at, 'created_at' );
	}
}
