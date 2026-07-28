<?php
/**
 * Batch Tag inventory read port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;

/**
 * Reads only fields approved for the manufacturing inventory projection.
 */
interface BatchTagInventoryReader {
	/**
	 * Return one stable page ordered by public Tag ID.
	 *
	 * @param int                          $batch_id Batch identifier.
	 * @param BatchTagInventoryCursor|null $cursor Previous cursor.
	 * @param PageSize                     $page_size Bounded page size.
	 */
	public function list_for_batch(
		int $batch_id,
		?BatchTagInventoryCursor $cursor,
		PageSize $page_size
	): BatchTagInventoryPage;
}
