<?php
/**
 * New encrypted Message data.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Value\MessageCiphertext;
use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;

/**
 * Immutable encrypted Message persistence data.
 */
final readonly class NewMessageRecord {
	/**
	 * Create Message persistence data.
	 *
	 * @param int                    $conversation_id Conversation identifier.
	 * @param MessageSenderRole      $sender_role Sender role.
	 * @param MessageCiphertext      $body_ciphertext Opaque encrypted body.
	 * @param DeliveryStatus         $delivery_status Persisted delivery state.
	 * @param string|null            $provider_message_id Provider identifier.
	 * @param DateTimeImmutable|null $delivered_at UTC confirmed-delivery time.
	 * @param DateTimeImmutable      $created_at UTC creation time.
	 */
	public function __construct(
		public int $conversation_id,
		public MessageSenderRole $sender_role,
		public MessageCiphertext $body_ciphertext,
		public DeliveryStatus $delivery_status,
		public ?string $provider_message_id,
		public ?DateTimeImmutable $delivered_at,
		public DateTimeImmutable $created_at
	) {
		RecordValidator::positive_id( $this->conversation_id, 'conversation_id' );
		RecordValidator::nullable_ascii( $this->provider_message_id, 191, 'provider_message_id' );
		RecordValidator::nullable_utc( $this->delivered_at, 'delivered_at' );
		RecordValidator::utc( $this->created_at, 'created_at' );
	}
}
