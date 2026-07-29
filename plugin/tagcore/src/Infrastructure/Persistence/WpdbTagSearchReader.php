<?php
/**
 * WordPress database administrative Tag search reader.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Tag\TagSearchCriteria;
use ReturnTag\TagCore\Application\Tag\TagSearchCursor;
use ReturnTag\TagCore\Application\Tag\TagSearchItem;
use ReturnTag\TagCore\Application\Tag\TagSearchMode;
use ReturnTag\TagCore\Application\Tag\TagSearchPage;
use ReturnTag\TagCore\Application\Tag\TagSearchReader;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Executes only exact Tag ID or exact Batch Code searches.
 */
final readonly class WpdbTagSearchReader implements TagSearchReader {
	/**
	 * Create the reader.
	 *
	 * @param WpdbGateway           $gateway Database gateway.
	 * @param TableNames            $tables Trusted table names.
	 * @param DatabaseDateTimeCodec $dates UTC datetime codec.
	 */
	public function __construct(
		private WpdbGateway $gateway,
		private TableNames $tables,
		private DatabaseDateTimeCodec $dates
	) {
	}

	/**
	 * Execute one exact-anchor search.
	 *
	 * @param TagSearchCriteria    $criteria Validated search criteria.
	 * @param TagSearchCursor|null $cursor Previous Batch-search cursor.
	 * @param PageSize             $page_size Bounded page size.
	 */
	public function search(
		TagSearchCriteria $criteria,
		?TagSearchCursor $cursor,
		PageSize $page_size
	): TagSearchPage {
		$limit = TagSearchMode::TAG_ID === $criteria->mode ? 1 : $page_size->value + 1;
		$rows  = TagSearchMode::TAG_ID === $criteria->mode
			? $this->search_by_tag_id( $criteria, $limit )
			: $this->search_by_batch( $criteria, $cursor, $limit );

		$has_more = count( $rows ) > $page_size->value;

		if ( $has_more ) {
			array_pop( $rows );
		}

		$items = array_map( fn( array $row ): TagSearchItem => $this->hydrate( $row ), $rows );
		$last  = $has_more ? end( $items ) : false;
		$next  = $last instanceof TagSearchItem ? new TagSearchCursor( $last->tag_id ) : null;

		return new TagSearchPage( $items, $next );
	}

	/**
	 * Execute one exact Tag ID lookup.
	 *
	 * @param TagSearchCriteria $criteria Exact Tag ID criteria.
	 * @param int               $limit Fixed row limit.
	 * @return list<array<string, mixed>>
	 * @throws InvalidArgumentException When the criteria are inconsistent.
	 */
	private function search_by_tag_id( TagSearchCriteria $criteria, int $limit ): array {
		if ( null === $criteria->tag_id ) {
			throw new InvalidArgumentException( 'Tag ID search criteria are invalid.' );
		}

		return $this->gateway->rows(
			'SELECT t.tag_id, b.batch_id, b.batch_code, b.batch_status, b.activation_enabled AS batch_activation_enabled, t.tag_type, t.model_code, t.tag_status, t.lost_mode, t.activated_at, t.created_at, t.updated_at FROM %i AS t INNER JOIN %i AS b ON b.batch_id = t.batch_id WHERE t.tag_id = %s ORDER BY t.tag_id ASC LIMIT %d',
			array( $this->tables->tags(), $this->tables->batches(), $criteria->tag_id->value, $limit )
		);
	}

	/**
	 * Execute one exact Batch Code lookup with optional status and cursor.
	 *
	 * @param TagSearchCriteria    $criteria Exact Batch Code criteria.
	 * @param TagSearchCursor|null $cursor Previous cursor.
	 * @param int                  $limit Bounded row limit.
	 * @return list<array<string, mixed>>
	 * @throws InvalidArgumentException When the criteria are inconsistent.
	 */
	private function search_by_batch(
		TagSearchCriteria $criteria,
		?TagSearchCursor $cursor,
		int $limit
	): array {
		if ( null === $criteria->batch_code ) {
			throw new InvalidArgumentException( 'Batch search criteria are invalid.' );
		}

		if ( null === $criteria->tag_status && null === $cursor ) {
			return $this->gateway->rows(
				'SELECT t.tag_id, b.batch_id, b.batch_code, b.batch_status, b.activation_enabled AS batch_activation_enabled, t.tag_type, t.model_code, t.tag_status, t.lost_mode, t.activated_at, t.created_at, t.updated_at FROM %i AS b INNER JOIN %i AS t ON t.batch_id = b.batch_id WHERE b.batch_code = %s ORDER BY t.tag_id ASC LIMIT %d',
				array( $this->tables->batches(), $this->tables->tags(), $criteria->batch_code, $limit )
			);
		}

		if ( null !== $criteria->tag_status && null === $cursor ) {
			return $this->gateway->rows(
				'SELECT t.tag_id, b.batch_id, b.batch_code, b.batch_status, b.activation_enabled AS batch_activation_enabled, t.tag_type, t.model_code, t.tag_status, t.lost_mode, t.activated_at, t.created_at, t.updated_at FROM %i AS b INNER JOIN %i AS t ON t.batch_id = b.batch_id WHERE b.batch_code = %s AND t.tag_status = %s ORDER BY t.tag_id ASC LIMIT %d',
				array( $this->tables->batches(), $this->tables->tags(), $criteria->batch_code, $criteria->tag_status->value, $limit )
			);
		}

		if ( null === $criteria->tag_status ) {
			return $this->gateway->rows(
				'SELECT t.tag_id, b.batch_id, b.batch_code, b.batch_status, b.activation_enabled AS batch_activation_enabled, t.tag_type, t.model_code, t.tag_status, t.lost_mode, t.activated_at, t.created_at, t.updated_at FROM %i AS b INNER JOIN %i AS t ON t.batch_id = b.batch_id WHERE b.batch_code = %s AND t.tag_id > %s ORDER BY t.tag_id ASC LIMIT %d',
				array( $this->tables->batches(), $this->tables->tags(), $criteria->batch_code, $cursor->tag_id->value, $limit )
			);
		}

		return $this->gateway->rows(
			'SELECT t.tag_id, b.batch_id, b.batch_code, b.batch_status, b.activation_enabled AS batch_activation_enabled, t.tag_type, t.model_code, t.tag_status, t.lost_mode, t.activated_at, t.created_at, t.updated_at FROM %i AS b INNER JOIN %i AS t ON t.batch_id = b.batch_id WHERE b.batch_code = %s AND t.tag_status = %s AND t.tag_id > %s ORDER BY t.tag_id ASC LIMIT %d',
			array( $this->tables->batches(), $this->tables->tags(), $criteria->batch_code, $criteria->tag_status->value, $cursor->tag_id->value, $limit )
		);
	}

	/**
	 * Map one strict, narrow stored row.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @throws PersistenceMappingException When the stored projection is invalid.
	 */
	private function hydrate( array $row ): TagSearchItem {
		try {
			return new TagSearchItem(
				TagId::from_canonical( StoredRow::string( $row, 'tag_id' ) ),
				StoredRow::positive_int( $row, 'batch_id' ),
				StoredRow::string( $row, 'batch_code' ),
				StoredRow::enum( $row, 'batch_status', BatchStatus::class ),
				StoredRow::boolean( $row, 'batch_activation_enabled' ),
				StoredRow::enum( $row, 'tag_type', TagType::class ),
				StoredRow::nullable_string( $row, 'model_code' ),
				StoredRow::enum( $row, 'tag_status', TagStatus::class ),
				StoredRow::boolean( $row, 'lost_mode' ),
				$this->dates->parse_nullable( StoredRow::nullable_string( $row, 'activated_at' ) ),
				$this->dates->parse( StoredRow::string( $row, 'created_at' ) ),
				$this->dates->parse( StoredRow::string( $row, 'updated_at' ) )
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored Tag search item is invalid.' );
		}
	}
}
