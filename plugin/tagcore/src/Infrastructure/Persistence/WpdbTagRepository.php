<?php
/**
 * WordPress database Tag Repository.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceConstraintViolationException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Pagination\TagCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\TagPage;
use ReturnTag\TagCore\Application\Persistence\Record\NewTagRecord;
use ReturnTag\TagCore\Application\Persistence\Record\TagRecord;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Repository\TagRepository;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Persists immutable Tag identities and their initial storage snapshot.
 */
final class WpdbTagRepository implements TagRepository {
	/**
	 * Create the Repository.
	 *
	 * @param WpdbGateway           $gateway Database gateway.
	 * @param TableNames            $tables Trusted table names.
	 * @param DatabaseDateTimeCodec $dates UTC datetime codec.
	 */
	public function __construct(
		private readonly WpdbGateway $gateway,
		private readonly TableNames $tables,
		private readonly DatabaseDateTimeCodec $dates
	) {
	}

	/**
	 * Insert one Tag after Batch-snapshot verification.
	 *
	 * RT-109 exposes no Batch update or delete operation, so the verified
	 * snapshot cannot change through this persistence surface between the
	 * verification and insert.
	 *
	 * @param NewTagRecord $record New Tag data.
	 */
	public function insert( NewTagRecord $record ): TagRecord {
		$this->assert_batch_snapshot( $record );
		$this->gateway->insert_without_id(
			$this->tables->tags(),
			array(
				'tag_id'               => $record->tag_id,
				'batch_id'             => $record->batch_id,
				'owner_id'             => $record->owner_id,
				'tag_type'             => $record->tag_type->value,
				'model_code'           => $record->model_code,
				'item_name'            => $record->item_name,
				'public_label'         => $record->public_label,
				'tag_status'           => $record->tag_status->value,
				'lost_mode'            => $record->lost_mode ? 1 : 0,
				'lost_message'         => $record->lost_message,
				'owner_pairing_ack_at' => $this->dates->format_nullable( $record->owner_pairing_ack_at ),
				'activated_at'         => $this->dates->format_nullable( $record->activated_at ),
				'owner_changed_at'     => $this->dates->format_nullable( $record->owner_changed_at ),
				'last_scanned_at'      => $this->dates->format_nullable( $record->last_scanned_at ),
				'created_at'           => $this->dates->format( $record->created_at ),
				'updated_at'           => $this->dates->format( $record->updated_at ),
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return new TagRecord( $record );
	}

	/**
	 * Find one Tag by public identifier.
	 *
	 * @param string $tag_id Public Tag ID.
	 */
	public function find_by_tag_id( string $tag_id ): ?TagRecord {
		RecordValidator::tag_id( $tag_id );
		$row = $this->gateway->row(
			'SELECT * FROM %i WHERE tag_id = %s LIMIT 1',
			array( $this->tables->tags(), $tag_id )
		);

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Return one bounded Batch Tag page.
	 *
	 * @param int            $batch_id Batch identifier.
	 * @param TagCursor|null $cursor Previous cursor.
	 * @param PageSize       $page_size Bounded page size.
	 */
	public function list_by_batch( int $batch_id, ?TagCursor $cursor, PageSize $page_size ): TagPage {
		RecordValidator::positive_id( $batch_id, 'batch_id' );

		return $this->list_page( 'batch_id', $batch_id, $cursor, $page_size );
	}

	/**
	 * Return one bounded Owner Tag page.
	 *
	 * @param int            $owner_id WordPress User ID.
	 * @param TagCursor|null $cursor Previous cursor.
	 * @param PageSize       $page_size Bounded page size.
	 */
	public function list_by_owner( int $owner_id, ?TagCursor $cursor, PageSize $page_size ): TagPage {
		RecordValidator::positive_id( $owner_id, 'owner_id' );

		return $this->list_page( 'owner_id', $owner_id, $cursor, $page_size );
	}

	/**
	 * Verify the application-supplied Batch snapshot.
	 *
	 * @param NewTagRecord $record New Tag data.
	 * @throws PersistenceConstraintViolationException When Batch data is absent or inconsistent.
	 */
	private function assert_batch_snapshot( NewTagRecord $record ): void {
		$row = $this->gateway->row(
			'SELECT tag_type, model_code FROM %i WHERE batch_id = %d LIMIT 1',
			array( $this->tables->batches(), $record->batch_id )
		);

		if (
			null === $row
			|| StoredRow::string( $row, 'tag_type' ) !== $record->tag_type->value
			|| StoredRow::nullable_string( $row, 'model_code' ) !== $record->model_code
		) {
			throw new PersistenceConstraintViolationException( 'Referenced record is unavailable or inconsistent.' );
		}
	}

	/**
	 * Execute a stable bounded Tag query.
	 *
	 * @param string         $scope_column Trusted internal scope column.
	 * @param int            $scope_id Scope identifier.
	 * @param TagCursor|null $cursor Previous cursor.
	 * @param PageSize       $page_size Bounded page size.
	 */
	private function list_page( string $scope_column, int $scope_id, ?TagCursor $cursor, PageSize $page_size ): TagPage {
		$limit = $page_size->value + 1;

		if ( null === $cursor ) {
			$rows = $this->gateway->rows(
				"SELECT * FROM %i WHERE {$scope_column} = %d ORDER BY tag_status ASC, tag_id ASC LIMIT %d",
				array( $this->tables->tags(), $scope_id, $limit )
			);
		} else {
			$rows = $this->gateway->rows(
				"SELECT * FROM %i WHERE {$scope_column} = %d AND (tag_status > %s OR (tag_status = %s AND tag_id > %s)) ORDER BY tag_status ASC, tag_id ASC LIMIT %d",
				array(
					$this->tables->tags(),
					$scope_id,
					$cursor->tag_status->value,
					$cursor->tag_status->value,
					$cursor->tag_id,
					$limit,
				)
			);
		}

		$has_more = count( $rows ) > $page_size->value;

		if ( $has_more ) {
			array_pop( $rows );
		}

		$items = array_map( fn( array $row ): TagRecord => $this->hydrate( $row ), $rows );
		$last  = $has_more ? end( $items ) : false;
		$next  = $last instanceof TagRecord
			? new TagCursor( $last->data->tag_status, $last->data->tag_id )
			: null;

		return new TagPage( $items, $next );
	}

	/**
	 * Map one strict stored Tag row.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @throws PersistenceMappingException When stored data violates the contract.
	 */
	private function hydrate( array $row ): TagRecord {
		try {
			return new TagRecord(
				new NewTagRecord(
					StoredRow::string( $row, 'tag_id' ),
					StoredRow::positive_int( $row, 'batch_id' ),
					StoredRow::nullable_positive_int( $row, 'owner_id' ),
					StoredRow::enum( $row, 'tag_type', TagType::class ),
					StoredRow::nullable_string( $row, 'model_code' ),
					StoredRow::nullable_string( $row, 'item_name' ),
					StoredRow::nullable_string( $row, 'public_label' ),
					StoredRow::enum( $row, 'tag_status', TagStatus::class ),
					StoredRow::boolean( $row, 'lost_mode' ),
					StoredRow::nullable_string( $row, 'lost_message' ),
					$this->dates->parse_nullable( StoredRow::nullable_string( $row, 'owner_pairing_ack_at' ) ),
					$this->dates->parse_nullable( StoredRow::nullable_string( $row, 'activated_at' ) ),
					$this->dates->parse_nullable( StoredRow::nullable_string( $row, 'owner_changed_at' ) ),
					$this->dates->parse_nullable( StoredRow::nullable_string( $row, 'last_scanned_at' ) ),
					$this->dates->parse( StoredRow::string( $row, 'created_at' ) ),
					$this->dates->parse( StoredRow::string( $row, 'updated_at' ) )
				)
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored Tag record is invalid.' );
		}
	}
}
