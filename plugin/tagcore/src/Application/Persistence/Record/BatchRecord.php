<?php
/**
 * Stored Batch record.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * One persisted Batch row.
 */
final readonly class BatchRecord {
	/**
	 * Create a stored Batch record.
	 *
	 * @param int            $batch_id Batch identifier.
	 * @param NewBatchRecord $data Stored Batch data.
	 */
	public function __construct(
		public int $batch_id,
		public NewBatchRecord $data
	) {
		RecordValidator::positive_id( $this->batch_id, 'batch_id' );
	}
}
