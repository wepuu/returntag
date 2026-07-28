<?php
/**
 * List Batch Tag inventory application service.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Application\Batch\Exception\BatchTagInventoryNotFound;
use ReturnTag\TagCore\Application\Batch\Exception\BatchTagInventoryUnavailable;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Repository\BatchRepository;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;

/**
 * Exposes only complete, immutable manufacturing inventory sets.
 */
final readonly class ListBatchTagInventory {
	/**
	 * Create the query service.
	 *
	 * @param BatchRepository         $batches Batch persistence.
	 * @param BatchTagInventoryReader $inventory Narrow inventory reader.
	 */
	public function __construct(
		private BatchRepository $batches,
		private BatchTagInventoryReader $inventory
	) {
	}

	/**
	 * Return one complete Batch inventory page.
	 *
	 * @param int                          $batch_id Batch identifier.
	 * @param BatchTagInventoryCursor|null $cursor Previous cursor.
	 * @param PageSize                     $page_size Bounded page size.
	 * @throws BatchTagInventoryNotFound When the Batch does not exist.
	 * @throws BatchTagInventoryUnavailable While the inventory is incomplete.
	 */
	public function execute(
		int $batch_id,
		?BatchTagInventoryCursor $cursor,
		PageSize $page_size
	): BatchTagInventoryPage {
		$batch = $this->batches->find_by_id( $batch_id );

		if ( null === $batch ) {
			throw new BatchTagInventoryNotFound( 'Batch inventory is unavailable.' );
		}

		if (
			in_array( $batch->data->batch_status, array( BatchStatus::DRAFT, BatchStatus::GENERATING ), true )
			|| $batch->data->generated_quantity !== $batch->data->requested_quantity
		) {
			throw new BatchTagInventoryUnavailable( 'Batch inventory is unavailable.' );
		}

		return $this->inventory->list_for_batch( $batch_id, $cursor, $page_size );
	}
}
