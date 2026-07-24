<?php
/**
 * Stable correlated Event cursor.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Pagination;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * Orders correlated events by descending append identifier.
 */
final readonly class CorrelationEventCursor {
	/**
	 * Create a correlated Event cursor.
	 *
	 * @param int $event_id Last Event identifier.
	 */
	public function __construct( public int $event_id ) {
		RecordValidator::positive_id( $this->event_id, 'event_id' );
	}
}
