<?php
/**
 * WordPress database Batch Tag inventory reader.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Batch\BatchTagInventoryCursor;
use ReturnTag\TagCore\Application\Batch\BatchTagInventoryItem;
use ReturnTag\TagCore\Application\Batch\BatchTagInventoryPage;
use ReturnTag\TagCore\Application\Batch\BatchTagInventoryReader;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Selects only the three fields approved for RT-206 inventory output.
 */
final readonly class WpdbBatchTagInventoryReader implements BatchTagInventoryReader {
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
	 * Return one stable page ordered by Tag ID.
	 *
	 * @param int                          $batch_id Batch identifier.
	 * @param BatchTagInventoryCursor|null $cursor Previous cursor.
	 * @param PageSize                     $page_size Bounded page size.
	 */
	public function list_for_batch(
		int $batch_id,
		?BatchTagInventoryCursor $cursor,
		PageSize $page_size
	): BatchTagInventoryPage {
		RecordValidator::positive_id( $batch_id, 'batch_id' );
		$limit = $page_size->value + 1;

		if ( null === $cursor ) {
			$rows = $this->gateway->rows(
				'SELECT tag_id, tag_status, created_at FROM %i WHERE batch_id = %d ORDER BY tag_id ASC LIMIT %d',
				array( $this->tables->tags(), $batch_id, $limit )
			);
		} else {
			$rows = $this->gateway->rows(
				'SELECT tag_id, tag_status, created_at FROM %i WHERE batch_id = %d AND tag_id > %s ORDER BY tag_id ASC LIMIT %d',
				array( $this->tables->tags(), $batch_id, $cursor->tag_id->value, $limit )
			);
		}

		$has_more = count( $rows ) > $page_size->value;

		if ( $has_more ) {
			array_pop( $rows );
		}

		$items = array_map( fn( array $row ): BatchTagInventoryItem => $this->hydrate( $row ), $rows );
		$last  = $has_more ? end( $items ) : false;
		$next  = $last instanceof BatchTagInventoryItem
			? new BatchTagInventoryCursor( $last->tag_id )
			: null;

		return new BatchTagInventoryPage( $items, $next );
	}

	/**
	 * Map one strict narrow inventory row.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @throws PersistenceMappingException When the stored projection is invalid.
	 */
	private function hydrate( array $row ): BatchTagInventoryItem {
		try {
			return new BatchTagInventoryItem(
				TagId::from_canonical( StoredRow::string( $row, 'tag_id' ) ),
				StoredRow::enum( $row, 'tag_status', TagStatus::class ),
				$this->dates->parse( StoredRow::string( $row, 'created_at' ) )
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored Batch Tag inventory item is invalid.' );
		}
	}
}
