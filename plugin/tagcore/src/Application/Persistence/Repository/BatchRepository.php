<?php
/**
 * Batch persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Repository;

use ReturnTag\TagCore\Application\Persistence\Pagination\BatchCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\BatchPage;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
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
	 * @phpstan-impure Database state may change between calls.
	 */
	public function find_by_code( string $batch_code ): ?BatchRecord;

	/**
	 * Return one bounded Batch summary page ordered newest first.
	 *
	 * @param BatchCursor|null $cursor Previous cursor.
	 * @param PageSize         $page_size Bounded page size.
	 */
	public function list_summaries( ?BatchCursor $cursor, PageSize $page_size ): BatchPage;
}
