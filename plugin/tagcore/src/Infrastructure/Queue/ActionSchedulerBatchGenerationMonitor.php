<?php
/**
 * Action Scheduler Batch generation monitor.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ActionScheduler;
use Closure;
use ReturnTag\TagCore\Application\Batch\BatchGenerationQueueMonitor;
use ReturnTag\TagCore\Application\Batch\BatchGenerationQueueState;
use Throwable;

/**
 * Projects provider state without exposing queue payloads or failures.
 */
final class ActionSchedulerBatchGenerationMonitor implements BatchGenerationQueueMonitor {
	/**
	 * Inspect queued generation work for one Batch.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function inspect( int $batch_id ): BatchGenerationQueueState {
		if (
			$batch_id < 1
			|| ! class_exists( ActionScheduler::class )
			|| ! class_exists( 'ActionScheduler_Store' )
		) {
			return BatchGenerationQueueState::UNAVAILABLE;
		}

		try {
			$store         = ActionScheduler::store();
			$query_actions = array( $store, 'query_actions' );

			if ( ! is_callable( $query_actions ) ) {
				return BatchGenerationQueueState::UNAVAILABLE;
			}

			$query_actions = Closure::fromCallable( $query_actions );

			if ( $this->has_action( $query_actions, $batch_id, 'in-progress' ) ) {
				return BatchGenerationQueueState::RUNNING;
			}

			if ( $this->has_action( $query_actions, $batch_id, 'pending' ) ) {
				return BatchGenerationQueueState::SCHEDULED;
			}
		} catch ( Throwable ) {
			return BatchGenerationQueueState::UNAVAILABLE;
		}

		return BatchGenerationQueueState::IDLE;
	}

	/**
	 * Test one bounded provider status query.
	 *
	 * @param Closure $query_actions Safe store query.
	 * @param int     $batch_id Batch identifier.
	 * @param string  $status Provider status.
	 */
	private function has_action(
		Closure $query_actions,
		int $batch_id,
		string $status
	): bool {
		$count = $query_actions(
			array(
				'hook'                  => ActionSchedulerBatchGenerationScheduler::HOOK,
				'args'                  => array( 'batch_id' => $batch_id ),
				'partial_args_matching' => 'json',
				'group'                 => ActionSchedulerBatchGenerationScheduler::GROUP,
				'status'                => $status,
				'per_page'              => 1,
			),
			'count'
		);

		return is_numeric( $count ) && (int) $count > 0;
	}
}
