<?php
/**
 * Stable Tag search cursor.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Continues Batch-scoped results after one Tag ID.
 */
final readonly class TagSearchCursor {
	/**
	 * Create one cursor.
	 *
	 * @param TagId $tag_id Last Tag ID in the previous page.
	 */
	public function __construct( public TagId $tag_id ) {
	}
}
