<?php
/**
 * WordPress database Batch generation Repository.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use InvalidArgumentException;
use ReturnTag\TagCore\Application\Batch\BatchGenerationState;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Repository\BatchGenerationRepository;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Provides narrow locking and conditional progress writes.
 */
final class WpdbBatchGenerationRepository implements BatchGenerationRepository {
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
	 * Lock and return one Batch inside the caller-owned transaction.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function lock_by_id( int $batch_id ): ?BatchGenerationState {
		RecordValidator::positive_id( $batch_id, 'batch_id' );
		$row = $this->gateway->row(
			'SELECT batch_id, tag_type, model_code, requested_quantity, generated_quantity, batch_status, ' .
			'activation_enabled, updated_at FROM %i WHERE batch_id = %d LIMIT 1 FOR UPDATE',
			array( $this->tables->batches(), $batch_id )
		);

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Count committed Tags belonging to one Batch.
	 *
	 * @param int $batch_id Batch identifier.
	 * @throws PersistenceMappingException When the count cannot be mapped safely.
	 */
	public function count_tags( int $batch_id ): int {
		RecordValidator::positive_id( $batch_id, 'batch_id' );
		$row = $this->gateway->row(
			'SELECT COUNT(*) AS tag_count FROM %i WHERE batch_id = %d',
			array( $this->tables->tags(), $batch_id )
		);

		if ( null === $row ) {
			throw new PersistenceMappingException( 'Stored Batch generation count is invalid.' );
		}

		return StoredRow::unsigned_int( $row, 'tag_count' );
	}

	/**
	 * Atomically move a pristine draft Batch into generation.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param DateTimeImmutable $updated_at UTC transition time.
	 */
	public function mark_generating( int $batch_id, DateTimeImmutable $updated_at ): bool {
		RecordValidator::positive_id( $batch_id, 'batch_id' );
		RecordValidator::utc( $updated_at, 'updated_at' );

		return 1 === $this->gateway->execute(
			'UPDATE %i SET batch_status = %s, updated_at = %s ' .
			'WHERE batch_id = %d AND batch_status = %s AND generated_quantity = 0 AND activation_enabled = 0',
			array(
				$this->tables->batches(),
				BatchStatus::GENERATING->value,
				$this->dates->format( $updated_at ),
				$batch_id,
				BatchStatus::DRAFT->value,
			)
		);
	}

	/**
	 * Atomically record one committed Tag and optionally complete the Batch.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param int               $expected_quantity Expected committed quantity.
	 * @param bool              $complete Whether this is the final requested Tag.
	 * @param DateTimeImmutable $updated_at UTC update time.
	 */
	public function advance(
		int $batch_id,
		int $expected_quantity,
		bool $complete,
		DateTimeImmutable $updated_at
	): bool {
		RecordValidator::positive_id( $batch_id, 'batch_id' );
		RecordValidator::unsigned_int( $expected_quantity, 'expected_quantity' );
		RecordValidator::utc( $updated_at, 'updated_at' );
		$next_quantity = $expected_quantity + 1;
		$next_status   = $complete ? BatchStatus::GENERATED : BatchStatus::GENERATING;
		$target_clause = $complete ? 'requested_quantity = %d' : 'requested_quantity > %d';

		return 1 === $this->gateway->execute(
			'UPDATE %i SET generated_quantity = %d, batch_status = %s, updated_at = %s ' .
			"WHERE batch_id = %d AND batch_status = %s AND generated_quantity = %d AND {$target_clause}",
			array(
				$this->tables->batches(),
				$next_quantity,
				$next_status->value,
				$this->dates->format( $updated_at ),
				$batch_id,
				BatchStatus::GENERATING->value,
				$expected_quantity,
				$next_quantity,
			)
		);
	}

	/**
	 * Complete a generating Batch whose counter already reached its target.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param int               $expected_quantity Expected final quantity.
	 * @param DateTimeImmutable $updated_at UTC completion time.
	 */
	public function complete(
		int $batch_id,
		int $expected_quantity,
		DateTimeImmutable $updated_at
	): bool {
		RecordValidator::positive_id( $batch_id, 'batch_id' );
		RecordValidator::unsigned_int( $expected_quantity, 'expected_quantity' );
		RecordValidator::utc( $updated_at, 'updated_at' );

		return 1 === $this->gateway->execute(
			'UPDATE %i SET batch_status = %s, updated_at = %s ' .
			'WHERE batch_id = %d AND batch_status = %s AND generated_quantity = %d AND requested_quantity = %d',
			array(
				$this->tables->batches(),
				BatchStatus::GENERATED->value,
				$this->dates->format( $updated_at ),
				$batch_id,
				BatchStatus::GENERATING->value,
				$expected_quantity,
				$expected_quantity,
			)
		);
	}

	/**
	 * Hydrate one strict generation projection.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @throws PersistenceMappingException When stored data violates the contract.
	 */
	private function hydrate( array $row ): BatchGenerationState {
		try {
			return new BatchGenerationState(
				StoredRow::positive_int( $row, 'batch_id' ),
				StoredRow::enum( $row, 'tag_type', TagType::class ),
				StoredRow::nullable_string( $row, 'model_code' ),
				StoredRow::positive_int( $row, 'requested_quantity' ),
				StoredRow::unsigned_int( $row, 'generated_quantity' ),
				StoredRow::enum( $row, 'batch_status', BatchStatus::class ),
				StoredRow::boolean( $row, 'activation_enabled' ),
				$this->dates->parse( StoredRow::string( $row, 'updated_at' ) )
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored Batch generation state is invalid.' );
		}
	}
}
