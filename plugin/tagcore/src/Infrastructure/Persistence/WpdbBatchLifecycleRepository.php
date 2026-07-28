<?php
/**
 * WordPress database Batch lifecycle Repository.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use InvalidArgumentException;
use ReturnTag\TagCore\Application\Batch\BatchLifecycleRepository;
use ReturnTag\TagCore\Application\Batch\BatchLifecycleState;
use ReturnTag\TagCore\Application\Batch\BatchTagCounts;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Provides narrow locking, aggregate, and conditional lifecycle operations.
 */
final readonly class WpdbBatchLifecycleRepository implements BatchLifecycleRepository {
	/**
	 * Create the Repository.
	 *
	 * @param WpdbGateway           $gateway Safe database gateway.
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
	 * Find one Batch without a row lock.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function find_by_id( int $batch_id ): ?BatchLifecycleState {
		return $this->read_state( $batch_id, false );
	}

	/**
	 * Lock one Batch inside the caller-owned transaction.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function lock_by_id( int $batch_id ): ?BatchLifecycleState {
		return $this->read_state( $batch_id, true );
	}

	/**
	 * Return aggregate canonical Tag status counts.
	 *
	 * @param int $batch_id Batch identifier.
	 * @throws PersistenceMappingException When a stored status or count is invalid.
	 */
	public function count_tags_by_status( int $batch_id ): BatchTagCounts {
		RecordValidator::positive_id( $batch_id, 'batch_id' );
		$rows   = $this->gateway->rows(
			'SELECT tag_status, COUNT(*) AS tag_count FROM %i WHERE batch_id = %d GROUP BY tag_status',
			array( $this->tables->tags(), $batch_id )
		);
		$counts = array(
			TagStatus::UNREGISTERED->value => 0,
			TagStatus::ACTIVE->value       => 0,
			TagStatus::SUSPENDED->value    => 0,
			TagStatus::RETIRED->value      => 0,
		);

		foreach ( $rows as $row ) {
			$status = StoredRow::enum( $row, 'tag_status', TagStatus::class );
			$count  = StoredRow::unsigned_int( $row, 'tag_count' );

			$counts[ $status->value ] = $count;
		}

		return new BatchTagCounts(
			$counts[ TagStatus::UNREGISTERED->value ],
			$counts[ TagStatus::ACTIVE->value ],
			$counts[ TagStatus::SUSPENDED->value ],
			$counts[ TagStatus::RETIRED->value ]
		);
	}

	/**
	 * Return the latest audited export row count.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function latest_export_row_count( int $batch_id ): ?int {
		RecordValidator::positive_id( $batch_id, 'batch_id' );
		$row = $this->gateway->row(
			'SELECT row_count FROM %i WHERE batch_id = %d ORDER BY export_version DESC LIMIT 1',
			array( $this->tables->batch_exports(), $batch_id )
		);

		return null === $row ? null : StoredRow::unsigned_int( $row, 'row_count' );
	}

	/**
	 * Apply one atomic expected-state transition.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param BatchStatus       $expected_status Expected current status.
	 * @param BatchStatus       $target_status Target status.
	 * @param bool              $activation_enabled Target activation control.
	 * @param DateTimeImmutable $updated_at UTC transition time.
	 */
	public function transition(
		int $batch_id,
		BatchStatus $expected_status,
		BatchStatus $target_status,
		bool $activation_enabled,
		DateTimeImmutable $updated_at
	): bool {
		RecordValidator::positive_id( $batch_id, 'batch_id' );
		RecordValidator::utc( $updated_at, 'updated_at' );

		return 1 === $this->gateway->execute(
			'UPDATE %i SET batch_status = %s, activation_enabled = %d, updated_at = %s ' .
			'WHERE batch_id = %d AND batch_status = %s',
			array(
				$this->tables->batches(),
				$target_status->value,
				$activation_enabled ? 1 : 0,
				$this->dates->format( $updated_at ),
				$batch_id,
				$expected_status->value,
			)
		);
	}

	/**
	 * Read one narrow state, optionally locking it in a caller-owned transaction.
	 *
	 * @param int  $batch_id Batch identifier.
	 * @param bool $lock Whether to append a row lock.
	 * @throws PersistenceMappingException When stored state cannot be mapped.
	 */
	private function read_state( int $batch_id, bool $lock ): ?BatchLifecycleState {
		RecordValidator::positive_id( $batch_id, 'batch_id' );
		$query = 'SELECT batch_id, batch_code, requested_quantity, generated_quantity, batch_status, ' .
			'activation_enabled, updated_at FROM %i WHERE batch_id = %d LIMIT 1';

		if ( $lock ) {
			$query .= ' FOR UPDATE';
		}

		$row = $this->gateway->row( $query, array( $this->tables->batches(), $batch_id ) );

		if ( null === $row ) {
			return null;
		}

		try {
			$status = StoredRow::enum( $row, 'batch_status', BatchStatus::class );

			return new BatchLifecycleState(
				StoredRow::positive_int( $row, 'batch_id' ),
				StoredRow::string( $row, 'batch_code' ),
				StoredRow::positive_int( $row, 'requested_quantity' ),
				StoredRow::unsigned_int( $row, 'generated_quantity' ),
				$status,
				StoredRow::boolean( $row, 'activation_enabled' ),
				$this->dates->parse( StoredRow::string( $row, 'updated_at' ) )
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored Batch lifecycle state is invalid.' );
		}
	}
}
