<?php
/**
 * Atomic Batch generation step result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

/**
 * Internal result for one transaction-sized generation step.
 */
final readonly class BatchGenerationStepResult {
	/**
	 * Create the step result.
	 *
	 * @param bool $processed Whether one Tag was committed.
	 * @param int  $generated_quantity Total committed quantity.
	 * @param bool $completed Whether generation is complete.
	 * @param bool $stopped Whether the Batch no longer accepts generation work.
	 */
	public function __construct(
		public bool $processed,
		public int $generated_quantity,
		public bool $completed,
		public bool $stopped = false
	) {
	}
}
