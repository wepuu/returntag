<?php
/**
 * Bounded administrative Tag search page.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

/**
 * One deterministic page of narrow Tag projections.
 */
final readonly class TagSearchPage {
	/**
	 * Create one search page.
	 *
	 * @param array                $items Search results.
	 * @param TagSearchCursor|null $next_cursor Next cursor.
	 * @phpstan-param list<TagSearchItem> $items
	 */
	public function __construct(
		public array $items,
		public ?TagSearchCursor $next_cursor
	) {
	}
}
