<?php
/**
 * Stable Tag pagination cursor.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Pagination;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Tag\TagStatus;

/**
 * Orders Tag results by status and public Tag ID.
 */
final readonly class TagCursor {
	/**
	 * Create a Tag cursor.
	 *
	 * @param TagStatus $tag_status Last Tag status.
	 * @param string    $tag_id Last public Tag ID.
	 */
	public function __construct(
		public TagStatus $tag_status,
		public string $tag_id
	) {
		RecordValidator::tag_id( $this->tag_id );
	}
}
