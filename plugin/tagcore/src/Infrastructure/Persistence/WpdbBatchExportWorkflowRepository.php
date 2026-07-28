<?php
/**
 * WordPress database Batch export workflow Repository.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use InvalidArgumentException;
use ReturnTag\TagCore\Application\Batch\BatchExportState;
use ReturnTag\TagCore\Application\Batch\BatchExportWorkflowRepository;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\SmartNetwork;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Serializes export-version allocation through the parent Batch row.
 */
final readonly class WpdbBatchExportWorkflowRepository implements BatchExportWorkflowRepository {
	/**
	 * Create the workflow Repository.
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
	 * Lock and return one Batch inside the caller-owned transaction.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function lock_by_id( int $batch_id ): ?BatchExportState {
		RecordValidator::positive_id( $batch_id, 'batch_id' );
		$row = $this->gateway->row(
			'SELECT batch_id, batch_code, tag_type, model_code, smart_network, requested_quantity, ' .
			'generated_quantity, batch_status FROM %i WHERE batch_id = %d LIMIT 1 FOR UPDATE',
			array( $this->tables->batches(), $batch_id )
		);

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Count committed Tags belonging to one Batch.
	 *
	 * @param int $batch_id Batch identifier.
	 * @throws PersistenceMappingException When the count cannot be mapped.
	 */
	public function count_tags( int $batch_id ): int {
		RecordValidator::positive_id( $batch_id, 'batch_id' );
		$row = $this->gateway->row(
			'SELECT COUNT(*) AS tag_count FROM %i WHERE batch_id = %d',
			array( $this->tables->tags(), $batch_id )
		);

		if ( null === $row ) {
			throw new PersistenceMappingException( 'Stored Batch export count is invalid.' );
		}

		return StoredRow::unsigned_int( $row, 'tag_count' );
	}

	/**
	 * Atomically mark one complete generated Batch as exported.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param DateTimeImmutable $updated_at UTC transition time.
	 */
	public function mark_exported( int $batch_id, DateTimeImmutable $updated_at ): bool {
		RecordValidator::positive_id( $batch_id, 'batch_id' );
		RecordValidator::utc( $updated_at, 'updated_at' );

		return 1 === $this->gateway->execute(
			'UPDATE %i SET batch_status = %s, updated_at = %s ' .
			'WHERE batch_id = %d AND batch_status = %s AND generated_quantity = requested_quantity',
			array(
				$this->tables->batches(),
				BatchStatus::EXPORTED->value,
				$this->dates->format( $updated_at ),
				$batch_id,
				BatchStatus::GENERATED->value,
			)
		);
	}

	/**
	 * Map one strict locked export state.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @throws PersistenceMappingException When stored data violates the contract.
	 */
	private function hydrate( array $row ): BatchExportState {
		try {
			return new BatchExportState(
				StoredRow::positive_int( $row, 'batch_id' ),
				StoredRow::string( $row, 'batch_code' ),
				StoredRow::enum( $row, 'tag_type', TagType::class ),
				StoredRow::nullable_string( $row, 'model_code' ),
				StoredRow::enum( $row, 'smart_network', SmartNetwork::class ),
				StoredRow::positive_int( $row, 'requested_quantity' ),
				StoredRow::unsigned_int( $row, 'generated_quantity' ),
				StoredRow::enum( $row, 'batch_status', BatchStatus::class )
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored Batch export state is invalid.' );
		}
	}
}
