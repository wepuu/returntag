<?php
/**
 * Stable Batch Tag inventory cursor.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Continues a deterministic Batch inventory after one public Tag ID.
 */
final readonly class BatchTagInventoryCursor {
	/**
	 * Create the cursor.
	 *
	 * @param TagId $tag_id Last returned Tag ID.
	 */
	public function __construct( public TagId $tag_id ) {
	}
}
