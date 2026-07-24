<?php
/**
 * Bounded Message page.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Pagination;

use ReturnTag\TagCore\Application\Persistence\Record\MessageRecord;

/**
 * Typed Message pagination result.
 */
final readonly class MessagePage {
	/**
	 * Create a Message page.
	 *
	 * @param array              $items Page records.
	 * @param MessageCursor|null $next_cursor Next cursor.
	 * @phpstan-param list<MessageRecord> $items
	 */
	public function __construct(
		public array $items,
		public ?MessageCursor $next_cursor
	) {
	}
}
