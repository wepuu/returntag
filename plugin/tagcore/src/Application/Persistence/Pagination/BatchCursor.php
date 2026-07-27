<?php
/**
 * Stable Batch pagination cursor.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Pagination;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * Orders Batch summaries by descending internal identifier.
 */
final readonly class BatchCursor {
	/**
	 * Create a Batch cursor.
	 *
	 * @param int $batch_id Last Batch identifier.
	 */
	public function __construct( public int $batch_id ) {
		RecordValidator::positive_id( $this->batch_id, 'batch_id' );
	}
}
