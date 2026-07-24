<?php
/**
 * WordPress database Batch Repository.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\Record\BatchRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewBatchRecord;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Repository\BatchRepository;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\SmartNetwork;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Persists manufacturing Batches through a trusted custom table.
 */
final class WpdbBatchRepository implements BatchRepository {
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
	 * Insert one Batch.
	 *
	 * @param NewBatchRecord $record New Batch data.
	 */
	public function insert( NewBatchRecord $record ): BatchRecord {
		$batch_id = $this->gateway->insert(
			$this->tables->batches(),
			array(
				'batch_code'         => $record->batch_code,
				'tag_type'           => $record->tag_type->value,
				'model_code'         => $record->model_code,
				'smart_network'      => $record->smart_network->value,
				'manufacturer'       => $record->manufacturer,
				'sales_channel'      => $record->sales_channel,
				'requested_quantity' => $record->requested_quantity,
				'generated_quantity' => $record->generated_quantity,
				'batch_status'       => $record->batch_status->value,
				'activation_enabled' => $record->activation_enabled ? 1 : 0,
				'notes'              => $record->notes,
				'created_by'         => $record->created_by,
				'created_at'         => $this->dates->format( $record->created_at ),
				'updated_at'         => $this->dates->format( $record->updated_at ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		return new BatchRecord( $batch_id, $record );
	}

	/**
	 * Find one Batch by internal identifier.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function find_by_id( int $batch_id ): ?BatchRecord {
		RecordValidator::positive_id( $batch_id, 'batch_id' );
		$row = $this->gateway->row(
			'SELECT * FROM %i WHERE batch_id = %d LIMIT 1',
			array( $this->tables->batches(), $batch_id )
		);

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Find one Batch by canonical code.
	 *
	 * @param string $batch_code Batch code.
	 */
	public function find_by_code( string $batch_code ): ?BatchRecord {
		RecordValidator::ascii( $batch_code, 191, 'batch_code' );
		$row = $this->gateway->row(
			'SELECT * FROM %i WHERE batch_code = %s LIMIT 1',
			array( $this->tables->batches(), $batch_code )
		);

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Map one strict stored Batch row.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @throws PersistenceMappingException When stored data violates the contract.
	 */
	private function hydrate( array $row ): BatchRecord {
		try {
			return new BatchRecord(
				StoredRow::positive_int( $row, 'batch_id' ),
				new NewBatchRecord(
					StoredRow::string( $row, 'batch_code' ),
					StoredRow::enum( $row, 'tag_type', TagType::class ),
					StoredRow::nullable_string( $row, 'model_code' ),
					StoredRow::enum( $row, 'smart_network', SmartNetwork::class ),
					StoredRow::nullable_string( $row, 'manufacturer' ),
					StoredRow::nullable_string( $row, 'sales_channel' ),
					StoredRow::positive_int( $row, 'requested_quantity' ),
					StoredRow::unsigned_int( $row, 'generated_quantity' ),
					StoredRow::enum( $row, 'batch_status', BatchStatus::class ),
					StoredRow::boolean( $row, 'activation_enabled' ),
					StoredRow::nullable_string( $row, 'notes' ),
					StoredRow::positive_int( $row, 'created_by' ),
					$this->dates->parse( StoredRow::string( $row, 'created_at' ) ),
					$this->dates->parse( StoredRow::string( $row, 'updated_at' ) )
				)
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored Batch record is invalid.' );
		}
	}
}
