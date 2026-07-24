<?php
/**
 * In-memory Batch Repository test fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture;

use ReturnTag\TagCore\Application\Persistence\Pagination\BatchCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\BatchPage;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Record\BatchRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewBatchRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\BatchRepository;

/**
 * Stores one or more Batch records without a platform dependency.
 */
final class InMemoryBatchRepository implements BatchRepository {
	/**
	 * Stored Batches indexed by identifier.
	 *
	 * @var array<int, BatchRecord>
	 */
	public array $records = array();

	/**
	 * Insert one Batch.
	 *
	 * @param NewBatchRecord $record New Batch data.
	 */
	public function insert( NewBatchRecord $record ): BatchRecord {
		$batch = new BatchRecord( count( $this->records ) + 1, $record );

		$this->records[ $batch->batch_id ] = $batch;

		return $batch;
	}

	/**
	 * Find one Batch by identifier.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function find_by_id( int $batch_id ): ?BatchRecord {
		return $this->records[ $batch_id ] ?? null;
	}

	/**
	 * Find one Batch by code.
	 *
	 * @param string $batch_code Batch code.
	 */
	public function find_by_code( string $batch_code ): ?BatchRecord {
		foreach ( $this->records as $record ) {
			if ( $record->data->batch_code === $batch_code ) {
				return $record;
			}
		}

		return null;
	}

	/**
	 * Return an empty page; list behavior is not used by this fixture.
	 *
	 * @param BatchCursor|null $cursor Previous cursor.
	 * @param PageSize         $page_size Bounded page size.
	 */
	public function list_summaries( ?BatchCursor $cursor, PageSize $page_size ): BatchPage {
		unset( $cursor, $page_size );

		return new BatchPage( array(), null );
	}
}
