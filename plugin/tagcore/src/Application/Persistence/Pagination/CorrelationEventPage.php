<?php
/**
 * Bounded correlated Event page.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Pagination;

use ReturnTag\TagCore\Application\Persistence\Record\EventRecord;

/**
 * Typed correlated Event pagination result.
 */
final readonly class CorrelationEventPage {
	/**
	 * Create a correlated Event page.
	 *
	 * @param array                       $items Page records.
	 * @param CorrelationEventCursor|null $next_cursor Next cursor.
	 * @phpstan-param list<EventRecord> $items
	 */
	public function __construct(
		public array $items,
		public ?CorrelationEventCursor $next_cursor
	) {
	}
}
