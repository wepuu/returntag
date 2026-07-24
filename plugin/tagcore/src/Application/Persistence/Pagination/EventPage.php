<?php
/**
 * Bounded Event page.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Pagination;

use ReturnTag\TagCore\Application\Persistence\Record\EventRecord;

/**
 * Typed Event pagination result.
 */
final readonly class EventPage {
	/**
	 * Create an Event page.
	 *
	 * @param array            $items Page records.
	 * @param EventCursor|null $next_cursor Next cursor.
	 * @phpstan-param list<EventRecord> $items
	 */
	public function __construct(
		public array $items,
		public ?EventCursor $next_cursor
	) {
	}
}
