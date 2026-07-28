<?php
/**
 * Bounded Batch Tag inventory page.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

/**
 * One deterministic page of narrow inventory items.
 */
final readonly class BatchTagInventoryPage {
	/**
	 * Create the page.
	 *
	 * @param array                        $items Inventory items.
	 * @param BatchTagInventoryCursor|null $next_cursor Next cursor.
	 * @phpstan-param list<BatchTagInventoryItem> $items
	 */
	public function __construct(
		public array $items,
		public ?BatchTagInventoryCursor $next_cursor
	) {
	}
}
