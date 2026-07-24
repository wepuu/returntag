<?php
/**
 * Batch Export persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Repository;

use ReturnTag\TagCore\Application\Persistence\Pagination\BatchExportCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\BatchExportPage;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Record\BatchExportRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewBatchExportRecord;

/**
 * Append-only Batch Export audit persistence contract.
 */
interface BatchExportRepository {
	/**
	 * Append one immutable export audit record.
	 *
	 * @param NewBatchExportRecord $record New export data.
	 */
	public function append( NewBatchExportRecord $record ): BatchExportRecord;

	/**
	 * Find one export version for a Batch.
	 *
	 * @param int $batch_id Batch identifier.
	 * @param int $export_version Export version.
	 */
	public function find_by_batch_and_version( int $batch_id, int $export_version ): ?BatchExportRecord;

	/**
	 * Return one bounded Batch export page.
	 *
	 * @param int                    $batch_id Batch identifier.
	 * @param BatchExportCursor|null $cursor Previous cursor.
	 * @param PageSize               $page_size Bounded page size.
	 */
	public function list_by_batch( int $batch_id, ?BatchExportCursor $cursor, PageSize $page_size ): BatchExportPage;
}
