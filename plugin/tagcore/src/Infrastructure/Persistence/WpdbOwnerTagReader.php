<?php
/**
 * WordPress database current-Owner Tag reader.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Account\OwnerTagReader;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Pagination\TagCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\TagPage;
use ReturnTag\TagCore\Application\Persistence\Record\NewTagRecord;
use ReturnTag\TagCore\Application\Persistence\Record\TagRecord;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Keeps Owner identity in every Account query predicate.
 */
final readonly class WpdbOwnerTagReader implements OwnerTagReader {
	/**
	 * Create the current-Owner reader.
	 *
	 * @param WpdbGateway           $gateway Safe query gateway.
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
	 * Return one bounded current-Owner Tag page.
	 *
	 * @param int            $owner_id Current WordPress User ID.
	 * @param TagCursor|null $cursor Optional stable cursor.
	 * @param PageSize       $page_size Bounded page size.
	 */
	public function list_for_owner( int $owner_id, ?TagCursor $cursor, PageSize $page_size ): TagPage {
		RecordValidator::positive_id( $owner_id, 'owner_id' );
		$limit = $page_size->value + 1;
		$rows  = null === $cursor
			? $this->gateway->rows(
				'SELECT * FROM %i WHERE owner_id = %d ORDER BY tag_status ASC, tag_id ASC LIMIT %d',
				array( $this->tables->tags(), $owner_id, $limit )
			)
			: $this->gateway->rows(
				'SELECT * FROM %i WHERE owner_id = %d AND (tag_status > %s OR (tag_status = %s AND tag_id > %s)) ORDER BY tag_status ASC, tag_id ASC LIMIT %d',
				array( $this->tables->tags(), $owner_id, $cursor->tag_status->value, $cursor->tag_status->value, $cursor->tag_id, $limit )
			);

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
	 * Return one Tag only when it belongs to the current Owner.
	 *
	 * @param int   $owner_id Current WordPress User ID.
	 * @param TagId $tag_id Canonical public Tag ID.
	 */
	public function find_for_owner( int $owner_id, TagId $tag_id ): ?TagRecord {
		RecordValidator::positive_id( $owner_id, 'owner_id' );
		$row = $this->gateway->row(
			'SELECT * FROM %i WHERE owner_id = %d AND tag_id = %s LIMIT 1',
			array( $this->tables->tags(), $owner_id, $tag_id->value )
		);

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Hydrate one strict current-Owner Tag record.
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
			throw new PersistenceMappingException( 'Stored Owner Tag record is invalid.' );
		}
	}
}
