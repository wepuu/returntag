<?php
/**
 * Stored Batch Export record.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * One persisted immutable export audit row.
 */
final readonly class BatchExportRecord {
	/**
	 * Create a stored Batch Export record.
	 *
	 * @param int                  $export_id Export identifier.
	 * @param NewBatchExportRecord $data Stored export data.
	 */
	public function __construct(
		public int $export_id,
		public NewBatchExportRecord $data
	) {
		RecordValidator::positive_id( $this->export_id, 'export_id' );
	}
}
