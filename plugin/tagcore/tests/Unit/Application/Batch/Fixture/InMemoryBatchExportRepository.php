<?php
/**
 * In-memory Batch Export Repository fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture;

use ReturnTag\TagCore\Application\Persistence\Pagination\BatchExportCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\BatchExportPage;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Record\BatchExportRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewBatchExportRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\BatchExportRepository;

/**
 * Stores append-only export audit records without a database.
 */
final class InMemoryBatchExportRepository implements BatchExportRepository {
	/**
	 * Stored export records.
	 *
	 * @var list<BatchExportRecord>
	 */
	public array $records = array();

	/**
	 * Append one record.
	 *
	 * @param NewBatchExportRecord $record New export data.
	 */
	public function append( NewBatchExportRecord $record ): BatchExportRecord {
		$stored          = new BatchExportRecord( count( $this->records ) + 1, $record );
		$this->records[] = $stored;

		return $stored;
	}

	/**
	 * Find one version.
	 *
	 * @param int $batch_id Batch identifier.
	 * @param int $export_version Export version.
	 */
	public function find_by_batch_and_version( int $batch_id, int $export_version ): ?BatchExportRecord {
		foreach ( $this->records as $record ) {
			if ( $record->data->batch_id === $batch_id && $record->data->export_version === $export_version ) {
				return $record;
			}
		}

		return null;
	}

	/**
	 * Return newest records first.
	 *
	 * @param int                    $batch_id Batch identifier.
	 * @param BatchExportCursor|null $cursor Previous cursor.
	 * @param PageSize               $page_size Bounded page size.
	 */
	public function list_by_batch( int $batch_id, ?BatchExportCursor $cursor, PageSize $page_size ): BatchExportPage {
		$items = array_values(
			array_filter(
				$this->records,
				static fn( BatchExportRecord $record ): bool => $record->data->batch_id === $batch_id
					&& ( null === $cursor || $record->data->export_version < $cursor->export_version )
			)
		);
		usort(
			$items,
			static fn( BatchExportRecord $left, BatchExportRecord $right ): int =>
				$right->data->export_version <=> $left->data->export_version
		);
		$items = array_slice( $items, 0, $page_size->value );

		return new BatchExportPage( $items, null );
	}
}
