<?php
/**
 * In-memory Batch lifecycle Repository fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Batch\BatchLifecycleRepository;
use ReturnTag\TagCore\Application\Batch\BatchLifecycleState;
use ReturnTag\TagCore\Application\Batch\BatchTagCounts;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;

/**
 * Provides deterministic lifecycle state without a database.
 */
final class InMemoryBatchLifecycleRepository implements BatchLifecycleRepository {
	/**
	 * Whether the next conditional write succeeds.
	 *
	 * @var bool
	 */
	public bool $transition_succeeds = true;

	/**
	 * Number of transition attempts.
	 *
	 * @var int
	 */
	public int $transition_calls = 0;

	/**
	 * Create the Repository.
	 *
	 * @param BatchLifecycleState|null $state Current state.
	 * @param BatchTagCounts           $counts Aggregate Tag counts.
	 * @param int|null                 $export_row_count Latest audited row count.
	 */
	public function __construct(
		public ?BatchLifecycleState $state,
		public BatchTagCounts $counts,
		public ?int $export_row_count
	) {
	}

	/**
	 * Find current state.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function find_by_id( int $batch_id ): ?BatchLifecycleState {
		return $this->matches( $batch_id ) ? $this->state : null;
	}

	/**
	 * Lock current state.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function lock_by_id( int $batch_id ): ?BatchLifecycleState {
		return $this->find_by_id( $batch_id );
	}

	/**
	 * Return fixed counts.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function count_tags_by_status( int $batch_id ): BatchTagCounts {
		unset( $batch_id );

		return $this->counts;
	}

	/**
	 * Return the fixed latest export row count.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function latest_export_row_count( int $batch_id ): ?int {
		unset( $batch_id );

		return $this->export_row_count;
	}

	/**
	 * Apply one conditional in-memory transition.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param BatchStatus       $expected_status Expected status.
	 * @param BatchStatus       $target_status Target status.
	 * @param bool              $activation_enabled Activation control.
	 * @param DateTimeImmutable $updated_at UTC transition time.
	 */
	public function transition(
		int $batch_id,
		BatchStatus $expected_status,
		BatchStatus $target_status,
		bool $activation_enabled,
		DateTimeImmutable $updated_at
	): bool {
		++$this->transition_calls;

		if (
			! $this->transition_succeeds
			|| ! $this->matches( $batch_id )
			|| $this->state?->batch_status !== $expected_status
		) {
			return false;
		}

		$this->state = new BatchLifecycleState(
			$batch_id,
			$this->state->batch_code,
			$this->state->requested_quantity,
			$this->state->generated_quantity,
			$target_status,
			$activation_enabled,
			$updated_at
		);

		return true;
	}

	/**
	 * Determine whether the fixture contains the requested Batch.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	private function matches( int $batch_id ): bool {
		return null !== $this->state && $this->state->batch_id === $batch_id;
	}
}
