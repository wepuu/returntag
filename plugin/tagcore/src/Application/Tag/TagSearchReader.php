<?php
/**
 * Administrative Tag search read port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;

/**
 * Reads only the RT-209 projection through bounded exact-anchor searches.
 */
interface TagSearchReader {
	/**
	 * Return one stable, bounded page for validated exact-match criteria.
	 *
	 * @param TagSearchCriteria    $criteria Validated criteria.
	 * @param TagSearchCursor|null $cursor Previous page cursor.
	 * @param PageSize             $page_size Bounded page size.
	 */
	public function search(
		TagSearchCriteria $criteria,
		?TagSearchCursor $cursor,
		PageSize $page_size
	): TagSearchPage;
}
