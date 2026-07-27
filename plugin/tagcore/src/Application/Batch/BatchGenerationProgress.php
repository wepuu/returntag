<?php
/**
 * Administrative Batch generation progress result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use DateTimeImmutable;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;

/**
 * Computed, privacy-safe progress returned by the application query.
 */
final readonly class BatchGenerationProgress {
	/**
	 * Number of requested IDs not yet committed.
	 *
	 * @var int
	 */
	public int $remaining_quantity;

	/**
	 * Number of persisted failed IDs.
	 *
	 * RT-204 never persists a failed candidate, so this remains zero.
	 *
	 * @var int
	 */
	public int $failed_quantity;

	/**
	 * Whole-number committed completion percentage.
	 *
	 * @var int
	 */
	public int $progress_percent;

	/**
	 * Whether first generation can start.
	 *
	 * @var bool
	 */
	public bool $can_start;

	/**
	 * Whether generation can be safely rescheduled.
	 *
	 * @var bool
	 */
	public bool $can_retry;

	/**
	 * Recommended client polling interval, or zero when polling should stop.
	 *
	 * @var int
	 */
	public int $poll_after_ms;

	/**
	 * Create a progress result from validated stored state.
	 *
	 * @param int                       $batch_id Batch identifier.
	 * @param BatchStatus               $batch_status Current Batch state.
	 * @param int                       $requested_quantity Target quantity.
	 * @param int                       $generated_quantity Committed quantity.
	 * @param bool                      $activation_enabled Batch activation control.
	 * @param DateTimeImmutable|null    $started_at Audited generation start.
	 * @param DateTimeImmutable|null    $completed_at Audited generation completion.
	 * @param DateTimeImmutable         $last_progress_at Last committed Batch update.
	 * @param BatchGenerationQueueState $queue_state Derived queue state.
	 */
	public function __construct(
		public int $batch_id,
		public BatchStatus $batch_status,
		public int $requested_quantity,
		public int $generated_quantity,
		public bool $activation_enabled,
		public ?DateTimeImmutable $started_at,
		public ?DateTimeImmutable $completed_at,
		public DateTimeImmutable $last_progress_at,
		public BatchGenerationQueueState $queue_state
	) {
		$this->remaining_quantity = max( 0, $this->requested_quantity - $this->generated_quantity );
		$this->failed_quantity    = 0;
		$this->progress_percent   = min(
			100,
			intdiv( $this->generated_quantity * 100, $this->requested_quantity )
		);
		$this->can_start          = BatchStatus::DRAFT === $this->batch_status
			&& 0 === $this->generated_quantity
			&& ! $this->activation_enabled;
		$this->can_retry          = BatchStatus::GENERATING === $this->batch_status
			&& BatchGenerationQueueState::NEEDS_ATTENTION === $this->queue_state;
		$this->poll_after_ms      = in_array(
			$this->queue_state,
			array( BatchGenerationQueueState::SCHEDULED, BatchGenerationQueueState::RUNNING ),
			true
		) ? 3000 : 0;
	}
}
