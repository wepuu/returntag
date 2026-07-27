<?php
/**
 * Fixed Batch generation queue monitor.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture;

use ReturnTag\TagCore\Application\Batch\BatchGenerationQueueMonitor;
use ReturnTag\TagCore\Application\Batch\BatchGenerationQueueState;

/**
 * Returns one configured operational state.
 */
final readonly class FixedBatchGenerationQueueMonitor implements BatchGenerationQueueMonitor {
	/**
	 * Create the monitor.
	 *
	 * @param BatchGenerationQueueState $state Observed state.
	 */
	public function __construct( private BatchGenerationQueueState $state ) {
	}

	/**
	 * Inspect one Batch.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function inspect( int $batch_id ): BatchGenerationQueueState {
		unset( $batch_id );

		return $this->state;
	}
}
