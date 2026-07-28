<?php
/**
 * List Batch export audit history.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Application\Batch\Exception\BatchExportNotFound;
use ReturnTag\TagCore\Application\Persistence\Pagination\BatchExportCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\BatchExportPage;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Repository\BatchExportRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\BatchRepository;

/**
 * Returns bounded immutable export audit records for one existing Batch.
 */
final readonly class ListBatchExports {
	/**
	 * Create the query.
	 *
	 * @param BatchRepository       $batches Batch persistence.
	 * @param BatchExportRepository $exports Export audit persistence.
	 */
	public function __construct(
		private BatchRepository $batches,
		private BatchExportRepository $exports
	) {
	}

	/**
	 * Return one export-history page.
	 *
	 * @param int                    $batch_id Batch identifier.
	 * @param BatchExportCursor|null $cursor Previous cursor.
	 * @param PageSize               $page_size Bounded page size.
	 * @throws BatchExportNotFound When the Batch does not exist.
	 */
	public function execute(
		int $batch_id,
		?BatchExportCursor $cursor,
		PageSize $page_size
	): BatchExportPage {
		if ( null === $this->batches->find_by_id( $batch_id ) ) {
			throw new BatchExportNotFound( 'Batch export history is unavailable.' );
		}

		return $this->exports->list_by_batch( $batch_id, $cursor, $page_size );
	}
}
