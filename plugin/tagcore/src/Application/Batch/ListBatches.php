<?php
/**
 * List Batches application service.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Application\Persistence\Pagination\BatchCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\BatchPage;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Repository\BatchRepository;

/**
 * Returns one bounded Batch summary page.
 */
final readonly class ListBatches {
	/**
	 * Create the query service.
	 *
	 * @param BatchRepository $batches Batch persistence.
	 */
	public function __construct( private BatchRepository $batches ) {
	}

	/**
	 * Return one page ordered by newest Batch first.
	 *
	 * @param BatchCursor|null $cursor Previous cursor.
	 * @param PageSize         $page_size Bounded page size.
	 */
	public function execute( ?BatchCursor $cursor, PageSize $page_size ): BatchPage {
		return $this->batches->list_summaries( $cursor, $page_size );
	}
}
