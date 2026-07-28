<?php
/**
 * Batch lifecycle persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use DateTimeImmutable;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;

/**
 * Exposes only reads and conditional writes required by RT-208.
 */
interface BatchLifecycleRepository {
	/**
	 * Find one Batch without acquiring a row lock.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function find_by_id( int $batch_id ): ?BatchLifecycleState;

	/**
	 * Lock one Batch inside the caller-owned transaction.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function lock_by_id( int $batch_id ): ?BatchLifecycleState;

	/**
	 * Return privacy-safe aggregate Tag status counts.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function count_tags_by_status( int $batch_id ): BatchTagCounts;

	/**
	 * Return the latest audited export row count.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function latest_export_row_count( int $batch_id ): ?int;

	/**
	 * Atomically apply one expected lifecycle transition.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param BatchStatus       $expected_status Expected current status.
	 * @param BatchStatus       $target_status Target status.
	 * @param bool              $activation_enabled Target activation control.
	 * @param DateTimeImmutable $updated_at UTC transition time.
	 */
	public function transition(
		int $batch_id,
		BatchStatus $expected_status,
		BatchStatus $target_status,
		bool $activation_enabled,
		DateTimeImmutable $updated_at
	): bool;
}
