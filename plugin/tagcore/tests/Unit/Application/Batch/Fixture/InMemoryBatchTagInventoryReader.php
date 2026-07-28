<?php
/**
 * In-memory Batch Tag inventory reader fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture;

use ReturnTag\TagCore\Application\Batch\BatchTagInventoryCursor;
use ReturnTag\TagCore\Application\Batch\BatchTagInventoryPage;
use ReturnTag\TagCore\Application\Batch\BatchTagInventoryReader;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;

/**
 * Records bounded inventory reads without a database.
 */
final class InMemoryBatchTagInventoryReader implements BatchTagInventoryReader {
	/**
	 * Number of read calls.
	 *
	 * @var int
	 */
	public int $calls = 0;

	/**
	 * Create the fixture.
	 *
	 * @param BatchTagInventoryPage $page Returned page.
	 */
	public function __construct( public BatchTagInventoryPage $page ) {
	}

	/**
	 * Return the configured page.
	 *
	 * @param int                          $batch_id Batch identifier.
	 * @param BatchTagInventoryCursor|null $cursor Previous cursor.
	 * @param PageSize                     $page_size Bounded page size.
	 */
	public function list_for_batch(
		int $batch_id,
		?BatchTagInventoryCursor $cursor,
		PageSize $page_size
	): BatchTagInventoryPage {
		unset( $batch_id, $cursor, $page_size );
		++$this->calls;

		return $this->page;
	}
}
