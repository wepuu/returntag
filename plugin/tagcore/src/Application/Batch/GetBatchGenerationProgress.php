<?php
/**
 * Batch generation progress query.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationIntegrityViolation;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationNotFound;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;

/**
 * Combines persisted progress with privacy-safe queue health.
 */
final readonly class GetBatchGenerationProgress {
	/**
	 * Create the query.
	 *
	 * @param BatchGenerationProgressReader $progress_reader Progress persistence.
	 * @param BatchGenerationQueueMonitor   $queue_monitor Queue health.
	 */
	public function __construct(
		private BatchGenerationProgressReader $progress_reader,
		private BatchGenerationQueueMonitor $queue_monitor
	) {
	}

	/**
	 * Return progress for one Batch.
	 *
	 * @param int $batch_id Batch identifier.
	 * @throws BatchGenerationNotFound When the Batch does not exist.
	 * @throws BatchGenerationIntegrityViolation When audited state is inconsistent.
	 */
	public function execute( int $batch_id ): BatchGenerationProgress {
		$snapshot = $this->progress_reader->find( $batch_id );

		if ( null === $snapshot ) {
			throw new BatchGenerationNotFound( 'Batch generation state was not found.' );
		}

		if (
			( BatchStatus::GENERATING === $snapshot->batch_status && null === $snapshot->started_at )
			|| (
				BatchStatus::GENERATED === $snapshot->batch_status
				&& ( null === $snapshot->started_at || null === $snapshot->completed_at )
			)
		) {
			throw new BatchGenerationIntegrityViolation( 'Batch generation audit state is inconsistent.' );
		}

		$observed_queue_state = $this->queue_monitor->inspect( $batch_id );
		$queue_state          = $this->derive_queue_state( $snapshot, $observed_queue_state );

		return new BatchGenerationProgress(
			$snapshot->batch_id,
			$snapshot->batch_status,
			$snapshot->requested_quantity,
			$snapshot->generated_quantity,
			$snapshot->activation_enabled,
			$snapshot->started_at,
			$snapshot->completed_at,
			$snapshot->updated_at,
			$queue_state
		);
	}

	/**
	 * Convert provider state into the stable administrative contract.
	 *
	 * @param BatchGenerationProgressSnapshot $snapshot Stored state.
	 * @param BatchGenerationQueueState       $observed_queue_state Provider state.
	 */
	private function derive_queue_state(
		BatchGenerationProgressSnapshot $snapshot,
		BatchGenerationQueueState $observed_queue_state
	): BatchGenerationQueueState {
		if ( BatchStatus::GENERATED === $snapshot->batch_status ) {
			return BatchGenerationQueueState::COMPLETE;
		}

		if ( BatchStatus::GENERATING !== $snapshot->batch_status ) {
			return BatchGenerationQueueState::IDLE;
		}

		if (
			in_array(
				$observed_queue_state,
				array(
					BatchGenerationQueueState::SCHEDULED,
					BatchGenerationQueueState::RUNNING,
					BatchGenerationQueueState::UNAVAILABLE,
				),
				true
			)
		) {
			return $observed_queue_state;
		}

		return BatchGenerationQueueState::NEEDS_ATTENTION;
	}
}
