<?php
/**
 * Metadata-only email delivery projection repository.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Email\EmailDeliveryRepository;
use ReturnTag\TagCore\Application\Email\EmailDeliveryStart;
use ReturnTag\TagCore\Application\Email\EmailDeliveryTransitionPolicy;
use ReturnTag\TagCore\Application\Email\EmailWebhookEvent;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/** Stores no recipient, subject, body, attachment, or complete provider payload. */
final class WpdbEmailDeliveryRepository implements EmailDeliveryRepository {
	/**
	 * Create the repository.
	 *
	 * @param WpdbGateway                   $gateway Safe prepared-query gateway.
	 * @param TableNames                    $tables Trusted table names.
	 * @param DatabaseDateTimeCodec         $dates UTC database codec.
	 * @param WpdbTransactionManager        $transactions Transaction manager.
	 * @param EmailDeliveryTransitionPolicy $policy Canonical state policy.
	 */
	public function __construct(
		private readonly WpdbGateway $gateway,
		private readonly TableNames $tables,
		private readonly DatabaseDateTimeCodec $dates,
		private readonly WpdbTransactionManager $transactions,
		private readonly EmailDeliveryTransitionPolicy $policy
	) {}

	/**
	 * Atomically create or resolve an idempotent dispatch row.
	 *
	 * @param string            $idempotency_key Opaque stable business key.
	 * @param string            $purpose Bounded message purpose.
	 * @param string            $provider Provider namespace.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @throws PersistenceMappingException When stored state is invalid.
	 */
	public function begin( string $idempotency_key, string $purpose, string $provider, DateTimeImmutable $now ): EmailDeliveryStart {
		$time    = $this->dates->format( $now );
		$created = 1 === $this->gateway->execute(
			'INSERT INTO %i (idempotency_key,purpose,provider,delivery_status,dispatch_attempt_count,created_at,updated_at) VALUES (%s,%s,%s,%s,1,%s,%s) ON DUPLICATE KEY UPDATE delivery_id=delivery_id',
			array( $this->tables->email_deliveries(), $idempotency_key, $purpose, $provider, DeliveryStatus::QUEUED->value, $time, $time )
		);
		$row     = $this->gateway->row( 'SELECT delivery_id,purpose,provider,provider_message_id,delivery_status,dispatch_attempt_count FROM %i WHERE idempotency_key=%s LIMIT 1', array( $this->tables->email_deliveries(), $idempotency_key ) );

		if ( null === $row || ( $row['purpose'] ?? null ) !== $purpose || ( $row['provider'] ?? null ) !== $provider ) {
			throw new PersistenceMappingException( 'Stored email delivery is invalid.' );
		}

		$status      = $this->status( $row['delivery_status'] ?? null );
		$delivery_id = $this->positive_int( $row['delivery_id'] ?? null );
		$message_id  = is_string( $row['provider_message_id'] ?? null ) ? $row['provider_message_id'] : null;
		$attempts    = $this->non_negative_int( $row['dispatch_attempt_count'] ?? null );

		return new EmailDeliveryStart( $delivery_id, $status, $message_id, true === $created && DeliveryStatus::QUEUED === $status && 1 === $attempts && null === $message_id );
	}

	/**
	 * Record provider acceptance.
	 *
	 * @param int               $delivery_id Internal delivery identifier.
	 * @param string            $provider_message_id Provider response identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_sent( int $delivery_id, string $provider_message_id, DateTimeImmutable $now ): bool {
		return 1 === $this->gateway->execute(
			'UPDATE %i SET provider_message_id=%s,delivery_status=%s,updated_at=%s WHERE delivery_id=%d AND delivery_status=%s AND provider_message_id IS NULL',
			array( $this->tables->email_deliveries(), $provider_message_id, DeliveryStatus::SENT->value, $this->dates->format( $now ), $delivery_id, DeliveryStatus::QUEUED->value )
		);
	}

	/**
	 * Fail an uncorrelated provider attempt.
	 *
	 * @param int               $delivery_id Internal delivery identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_failed( int $delivery_id, DateTimeImmutable $now ): bool {
		return 1 === $this->gateway->execute(
			'UPDATE %i SET delivery_status=%s,updated_at=%s WHERE delivery_id=%d AND delivery_status=%s AND provider_message_id IS NULL',
			array( $this->tables->email_deliveries(), DeliveryStatus::FAILED->value, $this->dates->format( $now ), $delivery_id, DeliveryStatus::QUEUED->value )
		);
	}

	/**
	 * Persist and converge one verified event.
	 *
	 * @param EmailWebhookEvent $event Allowlisted verified event.
	 * @param DateTimeImmutable $received_at Current UTC receive time.
	 */
	public function ingest( EmailWebhookEvent $event, DateTimeImmutable $received_at ): bool {
		$arguments = array( $this->tables->email_webhook_events(), $event->provider, $event->provider_event_id, $event->provider_message_id, $event->event_type );
		if ( null === $event->mapped_status ) {
			$query = 'INSERT INTO %i (provider,provider_event_id,provider_message_id,event_type,mapped_status,occurred_at,received_at,processing_attempt_count) VALUES (%s,%s,%s,%s,NULL,%s,%s,0) ON DUPLICATE KEY UPDATE provider_event_id=provider_event_id';
		} else {
			$query       = 'INSERT INTO %i (provider,provider_event_id,provider_message_id,event_type,mapped_status,occurred_at,received_at,processing_attempt_count) VALUES (%s,%s,%s,%s,%s,%s,%s,0) ON DUPLICATE KEY UPDATE provider_event_id=provider_event_id';
			$arguments[] = $event->mapped_status->value;
		}
		$arguments[] = $this->dates->format( $event->occurred_at );
		$arguments[] = $this->dates->format( $received_at );
		$inserted    = 1 === $this->gateway->execute( $query, $arguments );

		$this->converge_event( $event->provider, $event->provider_event_id, $received_at );
		return $inserted;
	}

	/**
	 * Retry bounded uncorrelated events.
	 *
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param int               $limit Maximum rows to inspect.
	 */
	public function converge_pending( DateTimeImmutable $now, int $limit ): int {
		$rows = $this->gateway->rows( 'SELECT provider,provider_event_id FROM %i WHERE processed_at IS NULL ORDER BY webhook_event_id ASC LIMIT %d', array( $this->tables->email_webhook_events(), max( 1, min( 100, $limit ) ) ) );
		foreach ( $rows as $row ) {
			if ( is_string( $row['provider'] ?? null ) && is_string( $row['provider_event_id'] ?? null ) ) {
				$this->converge_event( $row['provider'], $row['provider_event_id'], $now );
			}
		}
		return count( $rows );
	}

	/**
	 * Converge one persisted event inside a row-locking transaction.
	 *
	 * @param string            $provider Provider namespace.
	 * @param string            $provider_event_id Provider event identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	private function converge_event( string $provider, string $provider_event_id, DateTimeImmutable $now ): void {
		$this->transactions->transactional(
			function () use ( $provider, $provider_event_id, $now ): void {
				$event = $this->gateway->row( 'SELECT * FROM %i WHERE provider=%s AND provider_event_id=%s LIMIT 1 FOR UPDATE', array( $this->tables->email_webhook_events(), $provider, $provider_event_id ) );
				if ( null === $event || null !== ( $event['processed_at'] ?? null ) ) {
					return;
				}
				$mapped = $event['mapped_status'] ?? null;
				if ( null === $mapped ) {
					$this->mark_event_processed( $event, $now );
					return;
				}
				$candidate = $this->status( $mapped );
				$delivery  = $this->gateway->row( 'SELECT delivery_id,delivery_status,provider_event_at FROM %i WHERE provider=%s AND provider_message_id=%s LIMIT 1 FOR UPDATE', array( $this->tables->email_deliveries(), $provider, $event['provider_message_id'] ) );
				if ( null === $delivery ) {
					$this->increment_event_attempt( $event );
					return;
				}
				$current       = $this->status( $delivery['delivery_status'] ?? null );
				$current_event = is_string( $delivery['provider_event_at'] ?? null ) ? $this->dates->parse( $delivery['provider_event_at'] ) : null;
				$occurred      = $this->dates->parse( (string) $event['occurred_at'] );
				if ( $this->policy->allows( $current, $current_event, $candidate, $occurred ) ) {
					$delivered_at = DeliveryStatus::DELIVERED === $candidate ? $event['occurred_at'] : null;
					$this->gateway->execute( 'UPDATE %i SET delivery_status=%s,provider_event_at=%s,delivered_at=COALESCE(%s,delivered_at),updated_at=%s WHERE delivery_id=%d', array( $this->tables->email_deliveries(), $candidate->value, $event['occurred_at'], $delivered_at, $this->dates->format( $now ), $this->positive_int( $delivery['delivery_id'] ?? null ) ) );
				}
				$this->mark_event_processed( $event, $now );
			}
		);
	}

	/**
	 * Mark one event processed, including ignored and stale events.
	 *
	 * @param array<string, mixed> $event Stored event row.
	 * @param DateTimeImmutable    $now Current UTC time.
	 */
	private function mark_event_processed( array $event, DateTimeImmutable $now ): void {
		$this->gateway->execute( 'UPDATE %i SET processed_at=%s,processing_attempt_count=processing_attempt_count+1 WHERE webhook_event_id=%d AND processed_at IS NULL', array( $this->tables->email_webhook_events(), $this->dates->format( $now ), $this->positive_int( $event['webhook_event_id'] ?? null ) ) );
	}

	/**
	 * Record a valid event whose provider message is not correlated yet.
	 *
	 * @param array<string, mixed> $event Stored event row.
	 */
	private function increment_event_attempt( array $event ): void {
		$this->gateway->execute( 'UPDATE %i SET processing_attempt_count=processing_attempt_count+1 WHERE webhook_event_id=%d AND processed_at IS NULL', array( $this->tables->email_webhook_events(), $this->positive_int( $event['webhook_event_id'] ?? null ) ) );
	}

	/**
	 * Map a canonical stored status.
	 *
	 * @param mixed $value Stored scalar.
	 * @throws PersistenceMappingException When stored state is invalid.
	 */
	private function status( mixed $value ): DeliveryStatus {
		if ( ! is_string( $value ) ) {
			throw new PersistenceMappingException( 'Stored email delivery is invalid.' );
		}
		$status = DeliveryStatus::tryFrom( $value );
		if ( null === $status ) {
			throw new PersistenceMappingException( 'Stored email delivery is invalid.' );
		}
		return $status;
	}

	/**
	 * Map a positive database integer.
	 *
	 * @param mixed $value Stored scalar.
	 * @throws PersistenceMappingException When stored state is invalid.
	 */
	private function positive_int( mixed $value ): int {
		$integer = $this->non_negative_int( $value );
		if ( $integer < 1 ) {
			throw new PersistenceMappingException( 'Stored email delivery is invalid.' );
		}
		return $integer;
	}

	/**
	 * Map a non-negative database integer.
	 *
	 * @param mixed $value Stored scalar.
	 * @throws PersistenceMappingException When stored state is invalid.
	 */
	private function non_negative_int( mixed $value ): int {
		if ( is_int( $value ) && $value >= 0 ) {
			return $value;
		}
		if ( is_string( $value ) && ctype_digit( $value ) ) {
			return (int) $value;
		}
		throw new PersistenceMappingException( 'Stored email delivery is invalid.' );
	}
}
