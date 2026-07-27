<?php
/**
 * Generate Batch chunk result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

/**
 * Aggregate result without exposing generated Tag IDs.
 */
final readonly class GenerateBatchChunkResult {
	/**
	 * Create the result.
	 *
	 * @param int  $processed_quantity Tags committed by this action.
	 * @param int  $generated_quantity Total committed quantity.
	 * @param bool $completed Whether the Batch reached its target.
	 * @param bool $next_scheduled Whether another action was requested.
	 */
	public function __construct(
		public int $processed_quantity,
		public int $generated_quantity,
		public bool $completed,
		public bool $next_scheduled
	) {
	}
}
