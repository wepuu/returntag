<?php
/**
 * Allowlisted provider webhook event.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Email;

use DateTimeImmutable;
use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;

/** Contains no raw payload, address, subject, body, or attachment data. */
final readonly class EmailWebhookEvent {
	/**
	 * Create a verified, provider-neutral event.
	 *
	 * @param string              $provider Provider namespace.
	 * @param string              $provider_event_id Unique provider event identifier.
	 * @param string              $provider_message_id Provider email identifier.
	 * @param string              $event_type Allowlisted provider event type.
	 * @param DeliveryStatus|null $mapped_status Optional canonical state.
	 * @param DateTimeImmutable   $occurred_at Provider event time in UTC.
	 */
	public function __construct(
		public string $provider,
		public string $provider_event_id,
		public string $provider_message_id,
		public string $event_type,
		public ?DeliveryStatus $mapped_status,
		public DateTimeImmutable $occurred_at
	) {}
}
