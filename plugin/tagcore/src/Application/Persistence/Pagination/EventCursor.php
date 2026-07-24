<?php
/**
 * Stable Event cursor.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Pagination;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * Orders target events by descending UTC time and identifier.
 */
final readonly class EventCursor {
	/**
	 * Create an Event cursor.
	 *
	 * @param DateTimeImmutable $created_at Last Event creation time.
	 * @param int               $event_id Last Event identifier.
	 */
	public function __construct(
		public DateTimeImmutable $created_at,
		public int $event_id
	) {
		RecordValidator::utc( $this->created_at, 'created_at' );
		RecordValidator::positive_id( $this->event_id, 'event_id' );
	}
}
