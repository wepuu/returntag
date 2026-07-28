<?php
/**
 * WordPress database Batch export source reader.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Batch\BatchExportSourceReader;
use ReturnTag\TagCore\Application\Batch\BatchExportSourceTag;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Streams immutable manufacturing fields in canonical Tag ID order.
 */
final readonly class WpdbBatchExportSourceReader implements BatchExportSourceReader {
	private const CHUNK_SIZE = 500;

	/**
	 * Create the source reader.
	 *
	 * @param WpdbGateway $gateway Database gateway.
	 * @param TableNames  $tables Trusted table names.
	 */
	public function __construct(
		private WpdbGateway $gateway,
		private TableNames $tables
	) {
	}

	/**
	 * Iterate one Batch without hydrating private or mutable Tag fields.
	 *
	 * @param int $batch_id Batch identifier.
	 * @return iterable<BatchExportSourceTag>
	 */
	public function iterate_for_batch( int $batch_id ): iterable {
		RecordValidator::positive_id( $batch_id, 'batch_id' );
		$cursor = null;

		do {
			if ( null === $cursor ) {
				$rows = $this->gateway->rows(
					'SELECT tag_id, tag_type, model_code FROM %i WHERE batch_id = %d ORDER BY tag_id ASC LIMIT %d',
					array( $this->tables->tags(), $batch_id, self::CHUNK_SIZE )
				);
			} else {
				$rows = $this->gateway->rows(
					'SELECT tag_id, tag_type, model_code FROM %i WHERE batch_id = %d AND tag_id > %s ORDER BY tag_id ASC LIMIT %d',
					array( $this->tables->tags(), $batch_id, $cursor, self::CHUNK_SIZE )
				);
			}

			foreach ( $rows as $row ) {
				$item   = $this->hydrate( $row );
				$cursor = $item->tag_id->value;
				yield $item;
			}

			$row_count = count( $rows );
		} while ( self::CHUNK_SIZE === $row_count );
	}

	/**
	 * Map one narrow export row.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @throws PersistenceMappingException When the stored projection is invalid.
	 */
	private function hydrate( array $row ): BatchExportSourceTag {
		try {
			return new BatchExportSourceTag(
				TagId::from_canonical( StoredRow::string( $row, 'tag_id' ) ),
				StoredRow::enum( $row, 'tag_type', TagType::class ),
				StoredRow::nullable_string( $row, 'model_code' )
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored Batch export source is invalid.' );
		}
	}
}
