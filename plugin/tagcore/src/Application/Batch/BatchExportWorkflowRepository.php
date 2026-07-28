<?php
/**
 * Batch export workflow persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use DateTimeImmutable;

/**
 * Provides only the locking and state writes required by audited export.
 */
interface BatchExportWorkflowRepository {
	/**
	 * Lock one Batch inside the caller-owned transaction.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function lock_by_id( int $batch_id ): ?BatchExportState;

	/**
	 * Count committed Tags assigned to one Batch.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function count_tags( int $batch_id ): int;

	/**
	 * Atomically mark a complete generated Batch as exported.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param DateTimeImmutable $updated_at UTC transition time.
	 */
	public function mark_exported( int $batch_id, DateTimeImmutable $updated_at ): bool;
}
