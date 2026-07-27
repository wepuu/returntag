<?php
/**
 * Action Scheduler Batch generation adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Application\Batch\BatchGenerationScheduler;
use ReturnTag\TagCore\Application\Batch\BatchGenerationScheduleResult;
use ReturnTag\TagCore\Application\Batch\BatchGenerationScheduleStatus;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationQueueUnavailable;
use Throwable;

/**
 * Schedules low-priority, integer-only generation actions.
 */
final class ActionSchedulerBatchGenerationScheduler implements BatchGenerationScheduler {
	/**
	 * Generation action hook.
	 */
	public const HOOK = 'returntag_generate_batch_chunk';

	/**
	 * Action Scheduler group.
	 */
	public const GROUP = 'returntag-tag-generation';

	/**
	 * Lower-priority background work.
	 */
	public const PRIORITY = 20;

	/**
	 * Ensure one generation action is scheduled.
	 *
	 * @param int $batch_id Batch identifier.
	 * @param int $checkpoint Last committed generated quantity.
	 * @param int $retry_attempt Zero-based retry attempt.
	 * @param int $delay_seconds Delay before execution.
	 * @throws BatchGenerationQueueUnavailable When Action Scheduler cannot accept the action.
	 */
	public function schedule(
		int $batch_id,
		int $checkpoint,
		int $retry_attempt = 0,
		int $delay_seconds = 0
	): BatchGenerationScheduleResult {
		if ( $batch_id < 1 || $checkpoint < 0 || $retry_attempt < 0 || $delay_seconds < 0 ) {
			throw new BatchGenerationQueueUnavailable( 'Batch generation action could not be scheduled.' );
		}

		$arguments = array(
			'batch_id'      => $batch_id,
			'checkpoint'    => $checkpoint,
			'retry_attempt' => $retry_attempt,
		);

		try {
			if ( 0 === $delay_seconds ) {
				if ( ! function_exists( 'as_enqueue_async_action' ) ) {
					throw new BatchGenerationQueueUnavailable( 'Batch generation queue is unavailable.' );
				}

				$action_id = \as_enqueue_async_action(
					self::HOOK,
					$arguments,
					self::GROUP,
					true,
					self::PRIORITY
				);
			} else {
				if ( ! function_exists( 'as_schedule_single_action' ) ) {
					throw new BatchGenerationQueueUnavailable( 'Batch generation queue is unavailable.' );
				}

				$action_id = \as_schedule_single_action(
					time() + $delay_seconds,
					self::HOOK,
					$arguments,
					self::GROUP,
					true,
					self::PRIORITY
				);
			}
		} catch ( BatchGenerationQueueUnavailable $exception ) {
			throw $exception;
		} catch ( Throwable $exception ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The fixed exception is chained, not rendered.
			throw new BatchGenerationQueueUnavailable( 'Batch generation action could not be scheduled.', 0, $exception );
		}

		$status = is_int( $action_id ) && $action_id > 0
			? BatchGenerationScheduleStatus::QUEUED
			: BatchGenerationScheduleStatus::ALREADY_SCHEDULED;

		return new BatchGenerationScheduleResult( $status );
	}
}
