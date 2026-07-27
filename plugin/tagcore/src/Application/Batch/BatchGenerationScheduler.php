<?php
/**
 * Batch generation scheduler port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

/**
 * Schedules resumable background generation without exposing a provider.
 */
interface BatchGenerationScheduler {
	/**
	 * Ensure one generation action is scheduled.
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
	): BatchGenerationScheduleResult;
}
