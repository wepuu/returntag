<?php
/**
 * Batch generation persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Repository;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Batch\BatchGenerationState;

/**
 * Exposes only the locked and conditional writes required for generation.
 */
interface BatchGenerationRepository {
	/**
	 * Lock and return one Batch inside the caller-owned transaction.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function lock_by_id( int $batch_id ): ?BatchGenerationState;

	/**
	 * Count committed Tags belonging to one Batch.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function count_tags( int $batch_id ): int;

	/**
	 * Atomically move a pristine draft Batch into generation.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param DateTimeImmutable $updated_at UTC transition time.
	 */
	public function mark_generating( int $batch_id, DateTimeImmutable $updated_at ): bool;

	/**
	 * Atomically record one committed Tag and optionally complete the Batch.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param int               $expected_quantity Expected committed quantity.
	 * @param bool              $complete Whether this is the final requested Tag.
	 * @param DateTimeImmutable $updated_at UTC update time.
	 */
	public function advance(
		int $batch_id,
		int $expected_quantity,
		bool $complete,
		DateTimeImmutable $updated_at
	): bool;

	/**
	 * Complete a generating Batch whose counter already reached its target.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param int               $expected_quantity Expected final quantity.
	 * @param DateTimeImmutable $updated_at UTC completion time.
	 */
	public function complete(
		int $batch_id,
		int $expected_quantity,
		DateTimeImmutable $updated_at
	): bool;
}
