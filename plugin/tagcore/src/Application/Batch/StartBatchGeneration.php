<?php
/**
 * Start or resume background Batch generation.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationException;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationIntegrityViolation;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationNotAllowed;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationNotFound;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\BatchGenerationRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;

/**
 * Records the lifecycle start and ensures resumable work is queued.
 */
final readonly class StartBatchGeneration {
	/**
	 * Create the use case.
	 *
	 * @param BatchGenerationRepository $generation Batch generation persistence.
	 * @param EventRepository           $events Audit Event persistence.
	 * @param TransactionManager        $transactions Database transaction boundary.
	 * @param BatchGenerationScheduler  $scheduler Background scheduler.
	 * @param Clock                     $clock UTC clock.
	 */
	public function __construct(
		private BatchGenerationRepository $generation,
		private EventRepository $events,
		private TransactionManager $transactions,
		private BatchGenerationScheduler $scheduler,
		private Clock $clock
	) {
	}

	/**
	 * Start, resume, or idempotently observe completed generation.
	 *
	 * @param int $batch_id Batch identifier.
	 * @param int $actor_id Authorized WordPress User ID.
	 * @throws BatchGenerationNotAllowed When request data is invalid.
	 * @phpstan-throws BatchGenerationException When generation cannot start or be queued safely.
	 */
	public function execute( int $batch_id, int $actor_id ): StartBatchGenerationResult {
		if ( $batch_id < 1 || $actor_id < 1 ) {
			throw new BatchGenerationNotAllowed( 'Batch generation request is invalid.' );
		}

		$state = $this->transactions->transactional(
			function () use ( $batch_id, $actor_id ): BatchGenerationState {
				$state = $this->generation->lock_by_id( $batch_id );

				if ( null === $state ) {
					throw new BatchGenerationNotFound( 'Batch was not found.' );
				}

				$this->assert_storage_is_consistent( $state );

				if ( BatchStatus::GENERATED === $state->batch_status ) {
					return $state;
				}

				if ( BatchStatus::GENERATING === $state->batch_status ) {
					if ( $state->generated_quantity >= $state->requested_quantity || $state->activation_enabled ) {
						throw new BatchGenerationIntegrityViolation( 'Batch generation state is inconsistent.' );
					}

					return $state;
				}

				if (
					BatchStatus::DRAFT !== $state->batch_status
					|| 0 !== $state->generated_quantity
					|| $state->activation_enabled
				) {
					throw new BatchGenerationNotAllowed( 'Batch status does not allow generation.' );
				}

				$now = $this->clock->now();

				if ( ! $this->generation->mark_generating( $batch_id, $now ) ) {
					throw new BatchGenerationIntegrityViolation( 'Batch generation could not be started atomically.' );
				}

				$this->events->append(
					new NewEventRecord(
						'batch_generation_started',
						'user',
						$actor_id,
						'batch',
						(string) $batch_id,
						'success',
						null,
						EventMetadata::none(),
						$now
					)
				);

				return new BatchGenerationState(
					$state->batch_id,
					$state->tag_type,
					$state->model_code,
					$state->requested_quantity,
					$state->generated_quantity,
					BatchStatus::GENERATING,
					$state->activation_enabled,
					$now
				);
			}
		);

		if ( BatchStatus::GENERATED === $state->batch_status ) {
			return new StartBatchGenerationResult(
				$state->batch_id,
				$state->generated_quantity,
				$state->batch_status,
				null
			);
		}

		$schedule = $this->scheduler->schedule(
			$state->batch_id,
			$state->generated_quantity
		);

		return new StartBatchGenerationResult(
			$state->batch_id,
			$state->generated_quantity,
			$state->batch_status,
			$schedule->status
		);
	}

	/**
	 * Ensure the materialized counter matches committed Tag storage.
	 *
	 * @param BatchGenerationState $state Locked Batch state.
	 * @throws BatchGenerationIntegrityViolation When the counter and Tag storage differ.
	 */
	private function assert_storage_is_consistent( BatchGenerationState $state ): void {
		$tag_count = $this->generation->count_tags( $state->batch_id );

		if (
			$tag_count !== $state->generated_quantity
			|| (
				BatchStatus::GENERATED === $state->batch_status
				&& $state->generated_quantity !== $state->requested_quantity
			)
		) {
			throw new BatchGenerationIntegrityViolation( 'Batch generation storage is inconsistent.' );
		}
	}
}
