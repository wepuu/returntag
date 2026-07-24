<?php
/**
 * Stored encrypted Message record.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * One persisted encrypted Message row.
 */
final readonly class MessageRecord {
	/**
	 * Create a stored Message record.
	 *
	 * @param int              $message_id Message identifier.
	 * @param NewMessageRecord $data Stored Message data.
	 */
	public function __construct(
		public int $message_id,
		public NewMessageRecord $data
	) {
		RecordValidator::positive_id( $this->message_id, 'message_id' );
	}
}
