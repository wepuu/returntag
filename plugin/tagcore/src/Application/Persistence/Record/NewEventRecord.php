<?php
/**
 * New privacy-safe business Event data.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * Immutable Event persistence data.
 */
final readonly class NewEventRecord {
	/**
	 * Create Event persistence data.
	 *
	 * @param string            $event_type Event classification.
	 * @param string            $actor_type Actor classification.
	 * @param int|null          $actor_id Internal actor identifier.
	 * @param string            $target_type Target classification.
	 * @param string            $target_id Opaque target identifier.
	 * @param string            $event_result Event result code.
	 * @param string|null       $correlation_id Operation correlation identifier.
	 * @param EventMetadata     $metadata Validated privacy-safe metadata.
	 * @param DateTimeImmutable $created_at UTC creation time.
	 */
	public function __construct(
		public string $event_type,
		public string $actor_type,
		public ?int $actor_id,
		public string $target_type,
		public string $target_id,
		public string $event_result,
		public ?string $correlation_id,
		public EventMetadata $metadata,
		public DateTimeImmutable $created_at
	) {
		RecordValidator::ascii( $this->event_type, 64, 'event_type' );
		RecordValidator::ascii( $this->actor_type, 32, 'actor_type' );

		if ( null !== $this->actor_id ) {
			RecordValidator::positive_id( $this->actor_id, 'actor_id' );
		}

		RecordValidator::ascii( $this->target_type, 32, 'target_type' );
		RecordValidator::privacy_safe_event_identifier( $this->target_id, 191 );
		RecordValidator::ascii( $this->event_result, 32, 'event_result' );

		if ( null !== $this->correlation_id ) {
			RecordValidator::privacy_safe_event_identifier( $this->correlation_id, 64 );
		}

		RecordValidator::utc( $this->created_at, 'created_at' );
	}
}
