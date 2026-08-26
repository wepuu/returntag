<?php
/**
 * Resend webhook allowlist mapper.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Email;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use ReturnTag\TagCore\Application\Email\EmailWebhookEvent;
use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;

/** Discards the provider payload after extracting fixed metadata. */
final class ResendWebhookMapper {
	/**
	 * Map one verified raw payload.
	 *
	 * @param string $provider_event_id Verified Svix event identifier.
	 * @param string $raw_body Verified raw request body.
	 * @throws InvalidArgumentException When the allowlisted shape is invalid.
	 */
	public function map( string $provider_event_id, string $raw_body ): EmailWebhookEvent {
		$payload  = json_decode( $raw_body, true, 16, JSON_THROW_ON_ERROR );
		$type     = is_array( $payload ) ? ( $payload['type'] ?? null ) : null;
		$created  = is_array( $payload ) ? ( $payload['created_at'] ?? null ) : null;
		$data     = is_array( $payload ) ? ( $payload['data'] ?? null ) : null;
		$email_id = is_array( $data ) ? ( $data['email_id'] ?? null ) : null;

		if (
			! is_string( $type )
			|| 1 !== preg_match( '/^email\.[a-z_]{2,40}$/D', $type )
			|| ! is_string( $email_id )
			|| 1 !== preg_match( '/^[A-Za-z0-9_-]{1,191}$/D', $email_id )
			|| ! is_string( $created )
		) {
			throw new InvalidArgumentException( 'Email webhook payload is invalid.' );
		}

		try {
			$occurred = new DateTimeImmutable( $created );
		} catch ( \Throwable ) {
			throw new InvalidArgumentException( 'Email webhook payload is invalid.' );
		}

		return new EmailWebhookEvent( 'resend', $provider_event_id, $email_id, $type, $this->status( $type ), $occurred->setTimezone( new DateTimeZone( 'UTC' ) ) );
	}

	/**
	 * Map canonical delivery events; tracking events are intentionally ignored.
	 *
	 * @param string $type Allowlisted provider event type.
	 * @throws InvalidArgumentException When the event type is not explicitly supported.
	 */
	private function status( string $type ): ?DeliveryStatus {
		return match ( $type ) {
			'email.sent'             => DeliveryStatus::SENT,
			'email.delivered'        => DeliveryStatus::DELIVERED,
			'email.delivery_delayed' => DeliveryStatus::DEFERRED,
			'email.bounced'          => DeliveryStatus::BOUNCED,
			'email.complained'       => DeliveryStatus::COMPLAINED,
			'email.failed', 'email.suppressed' => DeliveryStatus::FAILED,
			'email.opened', 'email.clicked' => null,
			default => throw new InvalidArgumentException( 'Email webhook payload is invalid.' ),
		};
	}
}
