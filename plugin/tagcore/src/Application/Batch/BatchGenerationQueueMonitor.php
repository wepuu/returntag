<?php
/**
 * Batch generation queue monitoring port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

/**
 * Reports only the operational state needed by the administrative UI.
 */
interface BatchGenerationQueueMonitor {
	/**
	 * Inspect queued generation work for one Batch.
	 *
	 * Implementations return only idle, scheduled, running, or unavailable.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function inspect( int $batch_id ): BatchGenerationQueueState;
}
