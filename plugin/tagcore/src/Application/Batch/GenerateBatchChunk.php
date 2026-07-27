<?php
/**
 * Generate one resumable Batch chunk.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationException;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationIntegrityViolation;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationNotFound;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\BatchGenerationRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Application\Tag\GeneratedTagInput;
use ReturnTag\TagCore\Application\Tag\InsertGeneratedTag;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;

/**
 * Commits at most one bounded chunk while keeping every Tag atomic.
 */
final readonly class GenerateBatchChunk {
	/**
	 * Maximum Tags committed by one background action.
	 */
	public const CHUNK_SIZE = 100;

	/**
	 * Create the use case.
	 *
	 * @param BatchGenerationRepository $generation Batch generation persistence.
	 * @param InsertGeneratedTag        $insert_tag Collision-safe Tag insertion.
	 * @param EventRepository           $events Audit Event persistence.
	 * @param TransactionManager        $transactions Database transaction boundary.
	 * @param BatchGenerationScheduler  $scheduler Background scheduler.
	 * @param Clock                     $clock UTC clock.
	 */
	public function __construct(
		private BatchGenerationRepository $generation,
		private InsertGeneratedTag $insert_tag,
		private EventRepository $events,
		private TransactionManager $transactions,
		private BatchGenerationScheduler $scheduler,
		private Clock $clock
	) {
	}

	/**
	 * Execute one bounded generation action.
	 *
	 * @param int $batch_id Batch identifier.
	 * @param int $checkpoint Scheduler checkpoint.
	 * @param int $retry_attempt Current retry attempt.
	 * @throws BatchGenerationIntegrityViolation When action data is invalid.
	 * @phpstan-throws BatchGenerationException When the action cannot progress safely.
	 */
	public function execute(
		int $batch_id,
		int $checkpoint,
		int $retry_attempt = 0
	): GenerateBatchChunkResult {
		if ( $batch_id < 1 || $checkpoint < 0 || $retry_attempt < 0 ) {
			throw new BatchGenerationIntegrityViolation( 'Batch generation action is invalid.' );
		}

		$initial = $this->inspect( $batch_id, $checkpoint );

		if ( BatchStatus::GENERATED === $initial->batch_status ) {
			return new GenerateBatchChunkResult( 0, $initial->generated_quantity, true, false );
		}

		if ( BatchStatus::GENERATING !== $initial->batch_status ) {
			return new GenerateBatchChunkResult( 0, $initial->generated_quantity, false, false );
		}

		$processed = 0;
		$current   = $initial->generated_quantity;
		$completed = false;
		$stopped   = false;

		while ( $processed < self::CHUNK_SIZE && ! $completed && ! $stopped ) {
			$step = $this->transactions->transactional(
				fn(): BatchGenerationStepResult => $this->generate_one( $batch_id )
			);

			if ( $step->processed ) {
				++$processed;
			}

			$current   = $step->generated_quantity;
			$completed = $step->completed;
			$stopped   = $step->stopped;
		}

		if ( ! $completed && ! $stopped ) {
			$this->scheduler->schedule( $batch_id, $current );

			return new GenerateBatchChunkResult( $processed, $current, false, true );
		}

		return new GenerateBatchChunkResult( $processed, $current, $completed, false );
	}

	/**
	 * Lock and verify the resume checkpoint before processing.
	 *
	 * @param int $batch_id Batch identifier.
	 * @param int $checkpoint Scheduler checkpoint.
	 */
	private function inspect( int $batch_id, int $checkpoint ): BatchGenerationState {
		return $this->transactions->transactional(
			function () use ( $batch_id, $checkpoint ): BatchGenerationState {
				$state = $this->generation->lock_by_id( $batch_id );

				if ( null === $state ) {
					throw new BatchGenerationNotFound( 'Batch was not found.' );
				}

				$tag_count = $this->generation->count_tags( $batch_id );

				if (
					$tag_count !== $state->generated_quantity
					|| $checkpoint > $state->generated_quantity
				) {
					throw new BatchGenerationIntegrityViolation( 'Batch generation storage is inconsistent.' );
				}

				return $state;
			}
		);
	}

	/**
	 * Commit one Tag and its materialized counter.
	 *
	 * @param int $batch_id Batch identifier.
	 * @throws BatchGenerationIntegrityViolation When the locked progress cannot advance atomically.
	 * @throws BatchGenerationNotFound When the Batch no longer exists.
	 */
	private function generate_one( int $batch_id ): BatchGenerationStepResult {
		$state = $this->generation->lock_by_id( $batch_id );

		if ( null === $state ) {
			throw new BatchGenerationNotFound( 'Batch was not found.' );
		}

		if ( BatchStatus::GENERATED === $state->batch_status ) {
			return new BatchGenerationStepResult( false, $state->generated_quantity, true );
		}

		if ( BatchStatus::GENERATING !== $state->batch_status ) {
			return new BatchGenerationStepResult( false, $state->generated_quantity, false, true );
		}

		if ( $state->generated_quantity > $state->requested_quantity ) {
			throw new BatchGenerationIntegrityViolation( 'Batch generation counter exceeds its target.' );
		}

		if ( $state->generated_quantity === $state->requested_quantity ) {
			$now = $this->clock->now();

			if ( ! $this->generation->complete( $batch_id, $state->generated_quantity, $now ) ) {
				throw new BatchGenerationIntegrityViolation( 'Batch generation could not be completed atomically.' );
			}

			$this->append_completion_event( $batch_id, $now );

			return new BatchGenerationStepResult( false, $state->generated_quantity, true );
		}

		$now = $this->clock->now();
		$this->insert_tag->execute(
			new GeneratedTagInput(
				$state->batch_id,
				$state->tag_type,
				$state->model_code,
				$now
			)
		);

		$next_quantity = $state->generated_quantity + 1;
		$complete      = $next_quantity === $state->requested_quantity;

		if ( ! $this->generation->advance( $batch_id, $state->generated_quantity, $complete, $now ) ) {
			throw new BatchGenerationIntegrityViolation( 'Batch generation progress could not be advanced atomically.' );
		}

		if ( $complete ) {
			$this->append_completion_event( $batch_id, $now );
		}

		return new BatchGenerationStepResult( true, $next_quantity, $complete );
	}

	/**
	 * Append the one completion Event.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param DateTimeImmutable $created_at UTC completion time.
	 */
	private function append_completion_event( int $batch_id, DateTimeImmutable $created_at ): void {
		$this->events->append(
			new NewEventRecord(
				'batch_generation_completed',
				'system',
				null,
				'batch',
				(string) $batch_id,
				'success',
				null,
				EventMetadata::none(),
				$created_at
			)
		);
	}
}
