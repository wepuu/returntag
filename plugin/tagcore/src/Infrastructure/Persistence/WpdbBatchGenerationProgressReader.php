<?php
/**
 * WordPress database Batch generation progress projection.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Batch\BatchGenerationProgressReader;
use ReturnTag\TagCore\Application\Batch\BatchGenerationProgressSnapshot;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Reads only Batch counters, lifecycle state, and audited generation times.
 */
final readonly class WpdbBatchGenerationProgressReader implements BatchGenerationProgressReader {
	/**
	 * Create the projection reader.
	 *
	 * @param WpdbGateway           $gateway Safe database gateway.
	 * @param TableNames            $tables Trusted product table names.
	 * @param DatabaseDateTimeCodec $dates UTC datetime codec.
	 */
	public function __construct(
		private WpdbGateway $gateway,
		private TableNames $tables,
		private DatabaseDateTimeCodec $dates
	) {
	}

	/**
	 * Find progress for one Batch.
	 *
	 * @param int $batch_id Batch identifier.
	 * @throws InvalidArgumentException When the Batch identifier is invalid.
	 * @throws PersistenceMappingException When stored progress is inconsistent.
	 */
	public function find( int $batch_id ): ?BatchGenerationProgressSnapshot {
		if ( $batch_id < 1 ) {
			throw new InvalidArgumentException( 'Batch identifier is invalid.' );
		}

		$row = $this->gateway->row(
			'SELECT batch_id, requested_quantity, generated_quantity, batch_status, activation_enabled, updated_at ' .
			'FROM %i WHERE batch_id = %d',
			array( $this->tables->batches(), $batch_id )
		);

		if ( null === $row ) {
			return null;
		}

		$events = $this->gateway->rows(
			'SELECT event_type, created_at FROM %i ' .
			'WHERE target_type = %s AND target_id = %s AND event_type IN (%s, %s) ' .
			'ORDER BY created_at ASC, event_id ASC LIMIT %d',
			array(
				$this->tables->events(),
				'batch',
				(string) $batch_id,
				'batch_generation_started',
				'batch_generation_completed',
				3,
			)
		);

		if ( count( $events ) > 2 ) {
			throw new PersistenceMappingException( 'Stored Batch generation events are invalid.' );
		}

		$started_at   = null;
		$completed_at = null;

		foreach ( $events as $event ) {
			$event_type = StoredRow::string( $event, 'event_type' );
			$created_at = $this->dates->parse( StoredRow::string( $event, 'created_at' ) );

			if ( 'batch_generation_started' === $event_type && null === $started_at ) {
				$started_at = $created_at;
				continue;
			}

			if ( 'batch_generation_completed' === $event_type && null === $completed_at ) {
				$completed_at = $created_at;
				continue;
			}

			throw new PersistenceMappingException( 'Stored Batch generation events are invalid.' );
		}

		try {
			return new BatchGenerationProgressSnapshot(
				StoredRow::positive_int( $row, 'batch_id' ),
				StoredRow::positive_int( $row, 'requested_quantity' ),
				StoredRow::unsigned_int( $row, 'generated_quantity' ),
				StoredRow::enum( $row, 'batch_status', BatchStatus::class ),
				StoredRow::boolean( $row, 'activation_enabled' ),
				$started_at,
				$completed_at,
				$this->dates->parse( StoredRow::string( $row, 'updated_at' ) )
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored Batch generation progress is invalid.' );
		}
	}
}
