<?php
/**
 * Stable Message cursor.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Pagination;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * Orders messages chronologically by identifier.
 */
final readonly class MessageCursor {
	/**
	 * Create a Message cursor.
	 *
	 * @param int $message_id Last Message identifier.
	 */
	public function __construct( public int $message_id ) {
		RecordValidator::positive_id( $this->message_id, 'message_id' );
	}
}
