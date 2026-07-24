<?php
/**
 * Stored privacy-safe business Event record.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * One persisted business Event row.
 */
final readonly class EventRecord {
	/**
	 * Create a stored Event record.
	 *
	 * @param int            $event_id Event identifier.
	 * @param NewEventRecord $data Stored Event data.
	 */
	public function __construct(
		public int $event_id,
		public NewEventRecord $data
	) {
		RecordValidator::positive_id( $this->event_id, 'event_id' );
	}
}
