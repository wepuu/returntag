<?php
/**
 * Recording Batch generation scheduler fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture;

use ReturnTag\TagCore\Application\Batch\BatchGenerationScheduler;
use ReturnTag\TagCore\Application\Batch\BatchGenerationScheduleResult;
use ReturnTag\TagCore\Application\Batch\BatchGenerationScheduleStatus;

/**
 * Captures every requested action without a queue dependency.
 */
final class RecordingBatchGenerationScheduler implements BatchGenerationScheduler {
	/**
	 * Scheduled actions.
	 *
	 * @var list<array{batch_id: int, checkpoint: int, retry_attempt: int, delay_seconds: int}>
	 */
	public array $actions = array();

	/**
	 * Create the scheduler.
	 *
	 * @param BatchGenerationScheduleStatus $status Result returned for each action.
	 */
	public function __construct(
		private readonly BatchGenerationScheduleStatus $status = BatchGenerationScheduleStatus::QUEUED
	) {
	}

	/**
	 * Record one requested action.
	 *
	 * @param int $batch_id Batch identifier.
	 * @param int $checkpoint Last committed generated quantity.
	 * @param int $retry_attempt Zero-based retry attempt.
	 * @param int $delay_seconds Delay before execution.
	 */
	public function schedule(
		int $batch_id,
		int $checkpoint,
		int $retry_attempt = 0,
		int $delay_seconds = 0
	): BatchGenerationScheduleResult {
		$this->actions[] = array(
			'batch_id'      => $batch_id,
			'checkpoint'    => $checkpoint,
			'retry_attempt' => $retry_attempt,
			'delay_seconds' => $delay_seconds,
		);

		return new BatchGenerationScheduleResult( $this->status );
	}
}
