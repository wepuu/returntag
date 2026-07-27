<?php
/**
 * Start Batch generation result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Domain\Batch\BatchStatus;

/**
 * Safe administrative response for a generation request.
 */
final readonly class StartBatchGenerationResult {
	/**
	 * Create the result.
	 *
	 * @param int                                $batch_id Batch identifier.
	 * @param int                                $generated_quantity Committed quantity.
	 * @param BatchStatus                        $batch_status Current Batch status.
	 * @param BatchGenerationScheduleStatus|null $schedule_status Queue outcome, if applicable.
	 */
	public function __construct(
		public int $batch_id,
		public int $generated_quantity,
		public BatchStatus $batch_status,
		public ?BatchGenerationScheduleStatus $schedule_status
	) {
	}
}
