<?php
/**
 * Bounded Batch summary page.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Pagination;

use ReturnTag\TagCore\Application\Persistence\Record\BatchSummaryRecord;

/**
 * Typed Batch summary pagination result.
 */
final readonly class BatchPage {
	/**
	 * Create a Batch page.
	 *
	 * @param array            $items Page records.
	 * @param BatchCursor|null $next_cursor Next cursor.
	 * @phpstan-param list<BatchSummaryRecord> $items
	 */
	public function __construct(
		public array $items,
		public ?BatchCursor $next_cursor
	) {
	}
}
