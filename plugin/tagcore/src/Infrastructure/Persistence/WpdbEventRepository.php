<?php
/**
 * WordPress database Event Repository.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\EventIdentityPolicy;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\EventMetadataPolicy;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceConstraintViolationException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\Pagination\CorrelationEventCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\CorrelationEventPage;
use ReturnTag\TagCore\Application\Persistence\Pagination\EventCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\EventPage;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Record\EventRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Appends and reads privacy-safe business audit Events.
 */
final class WpdbEventRepository implements EventRepository {
	/**
	 * Create the Repository.
	 *
	 * @param WpdbGateway           $gateway Database gateway.
	 * @param TableNames            $tables Trusted table names.
	 * @param DatabaseDateTimeCodec $dates UTC datetime codec.
	 * @param EventMetadataPolicy   $metadata_policy Approved metadata policy.
	 * @param EventIdentityPolicy   $identity_policy Approved identity policy.
	 */
	public function __construct(
		private readonly WpdbGateway $gateway,
		private readonly TableNames $tables,
		private readonly DatabaseDateTimeCodec $dates,
		private readonly EventMetadataPolicy $metadata_policy,
		private readonly EventIdentityPolicy $identity_policy
	) {
	}

	/**
	 * Append one privacy-safe Event.
	 *
	 * @param NewEventRecord $record New Event data.
	 * @throws PersistenceConstraintViolationException When metadata is not approved.
	 */
	public function append( NewEventRecord $record ): EventRecord {
		if ( ! $this->identity_is_approved( $record ) ) {
			throw new PersistenceConstraintViolationException( 'Event identity is not approved.' );
		}

		try {
			$metadata = EventMetadata::from_stored_json(
				$record->event_type,
				$record->metadata->json(),
				$this->metadata_policy
			);
		} catch ( PersistenceMappingException ) {
			throw new PersistenceConstraintViolationException( 'Event metadata is not approved.' );
		}

		$event_id = $this->gateway->insert(
			$this->tables->events(),
			array(
				'event_type'     => $record->event_type,
				'actor_type'     => $record->actor_type,
				'actor_id'       => $record->actor_id,
				'target_type'    => $record->target_type,
				'target_id'      => $record->target_id,
				'event_result'   => $record->event_result,
				'correlation_id' => $record->correlation_id,
				'metadata_json'  => $metadata->json(),
				'created_at'     => $this->dates->format( $record->created_at ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return new EventRecord( $event_id, $record );
	}

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
	): EventPage {
		RecordValidator::ascii( $target_type, 32, 'target_type' );
		RecordValidator::privacy_safe_event_identifier( $target_id, 191 );

		return $this->list_page( 'target_type = %s AND target_id = %s', array( $target_type, $target_id ), $cursor, $page_size );
	}

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
	): CorrelationEventPage {
		RecordValidator::privacy_safe_event_identifier( $correlation_id, 64 );
		$limit = $page_size->value + 1;

		if ( null === $cursor ) {
			$rows = $this->gateway->rows(
				'SELECT * FROM %i WHERE correlation_id = %s ORDER BY event_id DESC LIMIT %d',
				array( $this->tables->events(), $correlation_id, $limit )
			);
		} else {
			$rows = $this->gateway->rows(
				'SELECT * FROM %i WHERE correlation_id = %s AND event_id < %d ORDER BY event_id DESC LIMIT %d',
				array( $this->tables->events(), $correlation_id, $cursor->event_id, $limit )
			);
		}

		$has_more = count( $rows ) > $page_size->value;

		if ( $has_more ) {
			array_pop( $rows );
		}

		$items = array_map( fn( array $row ): EventRecord => $this->hydrate( $row ), $rows );
		$last  = $has_more ? end( $items ) : false;
		$next  = $last instanceof EventRecord
			? new CorrelationEventCursor( $last->event_id )
			: null;

		return new CorrelationEventPage( $items, $next );
	}

	/**
	 * Execute a stable bounded Event query.
	 *
	 * @param string           $predicate Trusted internal predicate.
	 * @param array            $predicate_arguments Predicate values.
	 * @param EventCursor|null $cursor Previous cursor.
	 * @param PageSize         $page_size Bounded page size.
	 * @phpstan-param list<mixed> $predicate_arguments
	 */
	private function list_page(
		string $predicate,
		array $predicate_arguments,
		?EventCursor $cursor,
		PageSize $page_size
	): EventPage {
		$arguments = array_merge( array( $this->tables->events() ), $predicate_arguments );

		if ( null === $cursor ) {
			$query = "SELECT * FROM %i WHERE {$predicate} ORDER BY created_at DESC, event_id DESC LIMIT %d";
		} else {
			$query       = "SELECT * FROM %i WHERE {$predicate} AND (created_at < %s OR (created_at = %s AND event_id < %d)) ORDER BY created_at DESC, event_id DESC LIMIT %d";
			$cursor_time = $this->dates->format( $cursor->created_at );
			$arguments[] = $cursor_time;
			$arguments[] = $cursor_time;
			$arguments[] = $cursor->event_id;
		}

		$arguments[] = $page_size->value + 1;
		$rows        = $this->gateway->rows( $query, $arguments );
		$has_more    = count( $rows ) > $page_size->value;

		if ( $has_more ) {
			array_pop( $rows );
		}

		$items = array_map( fn( array $row ): EventRecord => $this->hydrate( $row ), $rows );
		$last  = $has_more ? end( $items ) : false;
		$next  = $last instanceof EventRecord
			? new EventCursor( $last->data->created_at, $last->event_id )
			: null;

		return new EventPage( $items, $next );
	}

	/**
	 * Map one strict stored Event row.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @throws PersistenceMappingException When stored data violates the contract.
	 */
	private function hydrate( array $row ): EventRecord {
		try {
			$event_type = StoredRow::string( $row, 'event_type' );

			$record = new NewEventRecord(
				$event_type,
				StoredRow::string( $row, 'actor_type' ),
				StoredRow::nullable_positive_int( $row, 'actor_id' ),
				StoredRow::string( $row, 'target_type' ),
				StoredRow::string( $row, 'target_id' ),
				StoredRow::string( $row, 'event_result' ),
				StoredRow::nullable_string( $row, 'correlation_id' ),
				EventMetadata::from_stored_json(
					$event_type,
					StoredRow::nullable_string( $row, 'metadata_json' ),
					$this->metadata_policy
				),
				$this->dates->parse( StoredRow::string( $row, 'created_at' ) )
			);

			if ( ! $this->identity_is_approved( $record ) ) {
				throw new PersistenceMappingException( 'Stored Event identity is not approved.' );
			}

			return new EventRecord(
				StoredRow::positive_int( $row, 'event_id' ),
				$record
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored Event record is invalid.' );
		}
	}

	/**
	 * Determine whether one Event identity is approved.
	 *
	 * @param NewEventRecord $record Event record.
	 */
	private function identity_is_approved( NewEventRecord $record ): bool {
		return $this->identity_policy->allows(
			$record->event_type,
			$record->actor_type,
			$record->actor_id,
			$record->target_type,
			$record->target_id,
			$record->correlation_id
		);
	}
}
