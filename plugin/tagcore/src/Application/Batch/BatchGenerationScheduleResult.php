<?php
/**
 * Batch generation schedule result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

/**
 * Provider-neutral background scheduling result.
 */
final readonly class BatchGenerationScheduleResult {
	/**
	 * Create the scheduling result.
	 *
	 * @param BatchGenerationScheduleStatus $status Scheduling outcome.
	 */
	public function __construct( public BatchGenerationScheduleStatus $status ) {
	}
}
