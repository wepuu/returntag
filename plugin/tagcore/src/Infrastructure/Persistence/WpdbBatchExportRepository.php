<?php
/**
 * WordPress database Batch Export Repository.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceConstraintViolationException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\Pagination\BatchExportCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\BatchExportPage;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Record\BatchExportRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewBatchExportRecord;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Repository\BatchExportRepository;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Appends immutable Batch Export audit records.
 */
final class WpdbBatchExportRepository implements BatchExportRepository {
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
	 * Append one immutable export audit record.
	 *
	 * @param NewBatchExportRecord $record New export data.
	 */
	public function append( NewBatchExportRecord $record ): BatchExportRecord {
		$this->assert_batch_exists( $record->batch_id );
		$export_id = $this->gateway->insert(
			$this->tables->batch_exports(),
			array(
				'batch_id'       => $record->batch_id,
				'export_version' => $record->export_version,
				'row_count'      => $record->row_count,
				'file_format'    => $record->file_format,
				'file_checksum'  => $record->file_checksum,
				'created_by'     => $record->created_by,
				'created_at'     => $this->dates->format( $record->created_at ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		return new BatchExportRecord( $export_id, $record );
	}

	/**
	 * Find one export version for a Batch.
	 *
	 * @param int $batch_id Batch identifier.
	 * @param int $export_version Export version.
	 */
	public function find_by_batch_and_version( int $batch_id, int $export_version ): ?BatchExportRecord {
		RecordValidator::positive_id( $batch_id, 'batch_id' );
		RecordValidator::positive_id( $export_version, 'export_version' );
		$row = $this->gateway->row(
			'SELECT * FROM %i WHERE batch_id = %d AND export_version = %d LIMIT 1',
			array( $this->tables->batch_exports(), $batch_id, $export_version )
		);

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Return one bounded Batch export page.
	 *
	 * @param int                    $batch_id Batch identifier.
	 * @param BatchExportCursor|null $cursor Previous cursor.
	 * @param PageSize               $page_size Bounded page size.
	 */
	public function list_by_batch( int $batch_id, ?BatchExportCursor $cursor, PageSize $page_size ): BatchExportPage {
		RecordValidator::positive_id( $batch_id, 'batch_id' );
		$limit = $page_size->value + 1;

		if ( null === $cursor ) {
			$rows = $this->gateway->rows(
				'SELECT * FROM %i WHERE batch_id = %d ORDER BY export_version DESC LIMIT %d',
				array( $this->tables->batch_exports(), $batch_id, $limit )
			);
		} else {
			$rows = $this->gateway->rows(
				'SELECT * FROM %i WHERE batch_id = %d AND export_version < %d ORDER BY export_version DESC LIMIT %d',
				array( $this->tables->batch_exports(), $batch_id, $cursor->export_version, $limit )
			);
		}

		$has_more = count( $rows ) > $page_size->value;

		if ( $has_more ) {
			array_pop( $rows );
		}

		$items = array_map( fn( array $row ): BatchExportRecord => $this->hydrate( $row ), $rows );
		$last  = $has_more ? end( $items ) : false;
		$next  = $last instanceof BatchExportRecord
			? new BatchExportCursor( $last->data->export_version )
			: null;

		return new BatchExportPage( $items, $next );
	}

	/**
	 * Verify the referenced Batch without exposing a generic relation API.
	 *
	 * @param int $batch_id Batch identifier.
	 * @throws PersistenceConstraintViolationException When the Batch is absent.
	 */
	private function assert_batch_exists( int $batch_id ): void {
		$row = $this->gateway->row(
			'SELECT batch_id FROM %i WHERE batch_id = %d LIMIT 1',
			array( $this->tables->batches(), $batch_id )
		);

		if ( null === $row ) {
			throw new PersistenceConstraintViolationException( 'Referenced record is unavailable or inconsistent.' );
		}
	}

	/**
	 * Map one strict stored Batch Export row.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @throws PersistenceMappingException When stored data violates the contract.
	 */
	private function hydrate( array $row ): BatchExportRecord {
		try {
			return new BatchExportRecord(
				StoredRow::positive_int( $row, 'export_id' ),
				new NewBatchExportRecord(
					StoredRow::positive_int( $row, 'batch_id' ),
					StoredRow::positive_int( $row, 'export_version' ),
					StoredRow::unsigned_int( $row, 'row_count' ),
					StoredRow::string( $row, 'file_format' ),
					StoredRow::string( $row, 'file_checksum' ),
					StoredRow::positive_int( $row, 'created_by' ),
					$this->dates->parse( StoredRow::string( $row, 'created_at' ) )
				)
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored Batch Export record is invalid.' );
		}
	}
}
