<?php
/**
 * Email delivery projection persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Email;

use DateTimeImmutable;

/** Persists metadata-only send and webhook state. */
interface EmailDeliveryRepository {
	/**
	 * Atomically create or resolve an idempotent dispatch record.
	 *
	 * @param string            $idempotency_key Opaque stable business key.
	 * @param string            $purpose Bounded message purpose.
	 * @param string            $provider Provider namespace.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function begin( string $idempotency_key, string $purpose, string $provider, DateTimeImmutable $now ): EmailDeliveryStart;

	/**
	 * Record provider acceptance, not confirmed delivery.
	 *
	 * @param int               $delivery_id Internal delivery identifier.
	 * @param string            $provider_message_id Provider response identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_sent( int $delivery_id, string $provider_message_id, DateTimeImmutable $now ): bool;

	/**
	 * Terminally fail a provider submission before correlation.
	 *
	 * @param int               $delivery_id Internal delivery identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_failed( int $delivery_id, DateTimeImmutable $now ): bool;

	/**
	 * Persist one verified event and converge it when correlation exists.
	 *
	 * @param EmailWebhookEvent $event Allowlisted verified event.
	 * @param DateTimeImmutable $received_at Current UTC receive time.
	 */
	public function ingest( EmailWebhookEvent $event, DateTimeImmutable $received_at ): bool;

	/**
	 * Retry a bounded set of valid, currently uncorrelated events.
	 *
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param int               $limit Maximum rows to inspect.
	 */
	public function converge_pending( DateTimeImmutable $now, int $limit ): int;
}
