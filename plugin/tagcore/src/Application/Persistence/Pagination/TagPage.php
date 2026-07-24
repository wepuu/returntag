<?php
/**
 * Bounded Tag page.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Pagination;

use ReturnTag\TagCore\Application\Persistence\Record\TagRecord;

/**
 * Typed Tag pagination result.
 */
final readonly class TagPage {
	/**
	 * Create a Tag page.
	 *
	 * @param array          $items Page records.
	 * @param TagCursor|null $next_cursor Next cursor.
	 * @phpstan-param list<TagRecord> $items
	 */
	public function __construct(
		public array $items,
		public ?TagCursor $next_cursor
	) {
	}
}
