<?php
/**
 * Batch export source reader port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

/**
 * Streams the narrow immutable fields approved for manufacturing export.
 */
interface BatchExportSourceReader {
	/**
	 * Iterate one Batch in canonical Tag ID order.
	 *
	 * @param int $batch_id Batch identifier.
	 * @return iterable<BatchExportSourceTag>
	 */
	public function iterate_for_batch( int $batch_id ): iterable;
}
