<?php
/**
 * Release Batch use case.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Domain\Batch\BatchLifecycleAction;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;

/**
 * Applies the approved release transition.
 */
final readonly class ReleaseBatch {
	/**
	 * Create the use case.
	 *
	 * @param ChangeBatchLifecycle $lifecycle Shared lifecycle transition service.
	 */
	public function __construct( private ChangeBatchLifecycle $lifecycle ) {
	}

	/**
	 * Release one Batch.
	 *
	 * @param int         $batch_id Batch identifier.
	 * @param int         $actor_id Authorized WordPress User ID.
	 * @param BatchStatus $expected_status Client-observed status.
	 */
	public function execute( int $batch_id, int $actor_id, BatchStatus $expected_status ): BatchLifecycleResult {
		return $this->lifecycle->execute(
			$batch_id,
			$actor_id,
			$expected_status,
			BatchLifecycleAction::RELEASE
		);
	}
}
