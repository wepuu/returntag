<?php
/**
 * Business Event persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Repository;

use ReturnTag\TagCore\Application\Persistence\Pagination\CorrelationEventCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\CorrelationEventPage;
use ReturnTag\TagCore\Application\Persistence\Pagination\EventCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\EventPage;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Record\EventRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;

/**
 * Append-and-query-only privacy-safe Event persistence contract.
 */
interface EventRepository {
	/**
	 * Append one privacy-safe Event.
	 *
	 * @param NewEventRecord $record New Event data.
	 */
	public function append( NewEventRecord $record ): EventRecord;

	/**
	 * Return one bounded target Event page.
	 *
	 * @param string           $target_type Target type.
	 * @param string           $target_id Target identifier.
	 * @param EventCursor|null $cursor Previous cursor.
	 * @param PageSize         $page_size Bounded page size.
	 */
	public function list_by_target(
		string $target_type,
		string $target_id,
		?EventCursor $cursor,
		PageSize $page_size
	): EventPage;

	/**
	 * Return one bounded correlated Event page.
	 *
	 * @param string                      $correlation_id Correlation identifier.
	 * @param CorrelationEventCursor|null $cursor Previous cursor.
	 * @param PageSize                    $page_size Bounded page size.
	 */
	public function list_by_correlation(
		string $correlation_id,
		?CorrelationEventCursor $cursor,
		PageSize $page_size
	): CorrelationEventPage;
}
