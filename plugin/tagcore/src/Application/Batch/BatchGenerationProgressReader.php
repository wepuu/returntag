<?php
/**
 * Batch generation progress persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

/**
 * Reads a narrow, privacy-safe Batch generation projection.
 */
interface BatchGenerationProgressReader {
	/**
	 * Find progress for one Batch.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function find( int $batch_id ): ?BatchGenerationProgressSnapshot;
}
