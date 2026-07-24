<?php
/**
 * Bounded Batch Export page.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Pagination;

use ReturnTag\TagCore\Application\Persistence\Record\BatchExportRecord;

/**
 * Typed Batch Export pagination result.
 */
final readonly class BatchExportPage {
	/**
	 * Create a Batch Export page.
	 *
	 * @param array                  $items Page records.
	 * @param BatchExportCursor|null $next_cursor Next cursor.
	 * @phpstan-param list<BatchExportRecord> $items
	 */
	public function __construct(
		public array $items,
		public ?BatchExportCursor $next_cursor
	) {
	}
}
