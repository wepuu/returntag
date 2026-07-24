<?php
/**
 * In-memory Event Repository test fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture;

use ReturnTag\TagCore\Application\Persistence\Pagination\CorrelationEventCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\CorrelationEventPage;
use ReturnTag\TagCore\Application\Persistence\Pagination\EventCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\EventPage;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Record\EventRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;

/**
 * Records appended Events without a platform dependency.
 */
final class InMemoryEventRepository implements EventRepository {
	/**
	 * Appended Events.
	 *
	 * @var list<EventRecord>
	 */
	public array $records = array();

	/**
	 * Append one Event.
	 *
	 * @param NewEventRecord $record New Event data.
	 */
	public function append( NewEventRecord $record ): EventRecord {
		$event = new EventRecord( count( $this->records ) + 1, $record );

		$this->records[] = $event;

		return $event;
	}

	/**
	 * Return an empty target page.
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
	): EventPage {
		unset( $target_type, $target_id, $cursor, $page_size );

		return new EventPage( array(), null );
	}

	/**
	 * Return an empty correlation page.
	 *
	 * @param string                      $correlation_id Correlation identifier.
	 * @param CorrelationEventCursor|null $cursor Previous cursor.
	 * @param PageSize                    $page_size Bounded page size.
	 */
	public function list_by_correlation(
		string $correlation_id,
		?CorrelationEventCursor $cursor,
		PageSize $page_size
	): CorrelationEventPage {
		unset( $correlation_id, $cursor, $page_size );

		return new CorrelationEventPage( array(), null );
	}
}
