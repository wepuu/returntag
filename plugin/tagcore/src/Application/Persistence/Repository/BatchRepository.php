<?php
/**
 * Batch persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Repository;

use ReturnTag\TagCore\Application\Persistence\Record\BatchRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewBatchRecord;

/**
 * Narrow manufacturing Batch persistence contract.
 */
interface BatchRepository {
	/**
	 * Insert one Batch.
	 *
	 * @param NewBatchRecord $record New Batch data.
	 */
	public function insert( NewBatchRecord $record ): BatchRecord;

	/**
	 * Find one Batch by internal identifier.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function find_by_id( int $batch_id ): ?BatchRecord;

	/**
	 * Find one Batch by canonical code.
	 *
	 * @param string $batch_code Batch code.
	 */
	public function find_by_code( string $batch_code ): ?BatchRecord;
}
