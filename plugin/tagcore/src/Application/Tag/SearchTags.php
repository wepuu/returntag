<?php
/**
 * Search Tags application service.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;

/**
 * Enforces pagination rules before delegating to the narrow read port.
 */
final readonly class SearchTags {
	/**
	 * Create the use case.
	 *
	 * @param TagSearchReader $reader Narrow persistence reader.
	 */
	public function __construct( private TagSearchReader $reader ) {
	}

	/**
	 * Execute one bounded read-only search.
	 *
	 * @param TagSearchCriteria    $criteria Validated search criteria.
	 * @param TagSearchCursor|null $cursor Previous Batch-search cursor.
	 * @param PageSize             $page_size Bounded page size.
	 * @throws InvalidArgumentException When exact Tag ID mode receives a cursor.
	 */
	public function execute(
		TagSearchCriteria $criteria,
		?TagSearchCursor $cursor,
		PageSize $page_size
	): TagSearchPage {
		if ( TagSearchMode::TAG_ID === $criteria->mode && null !== $cursor ) {
			throw new InvalidArgumentException( 'Exact Tag ID searches do not accept a cursor.' );
		}

		return $this->reader->search( $criteria, $cursor, $page_size );
	}
}
