<?php
/**
 * In-memory Batch generation Repository fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture;

use DateTimeImmutable;
use LogicException;
use ReturnTag\TagCore\Application\Batch\BatchGenerationState;
use ReturnTag\TagCore\Application\Persistence\Repository\BatchGenerationRepository;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;

/**
 * Models the narrow locked progress contract without WordPress.
 */
final class InMemoryBatchGenerationRepository implements BatchGenerationRepository {
	/**
	 * Number of lock reads.
	 *
	 * @var int
	 */
	public int $lock_calls = 0;

	/**
	 * Create the fixture.
	 *
	 * @param BatchGenerationState|null $state Stored Batch state.
	 * @param int                       $tag_count Committed Tag count.
	 */
	public function __construct(
		public ?BatchGenerationState $state,
		public int $tag_count
	) {
	}

	/**
	 * Return the current state while recording the lock request.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function lock_by_id( int $batch_id ): ?BatchGenerationState {
		++$this->lock_calls;

		return null !== $this->state && $this->state->batch_id === $batch_id
			? $this->state
			: null;
	}

	/**
	 * Return the configured committed Tag count.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function count_tags( int $batch_id ): int {
		unset( $batch_id );

		return $this->tag_count;
	}

	/**
	 * Move a pristine draft into generation.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param DateTimeImmutable $updated_at UTC transition time.
	 */
	public function mark_generating( int $batch_id, DateTimeImmutable $updated_at ): bool {
		if (
			null === $this->state
			|| $this->state->batch_id !== $batch_id
			|| BatchStatus::DRAFT !== $this->state->batch_status
			|| 0 !== $this->state->generated_quantity
			|| $this->state->activation_enabled
		) {
			return false;
		}

		$this->state = $this->replace( BatchStatus::GENERATING, 0, $updated_at );

		return true;
	}

	/**
	 * Advance one committed Tag.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param int               $expected_quantity Expected committed quantity.
	 * @param bool              $complete Whether this is the final Tag.
	 * @param DateTimeImmutable $updated_at UTC update time.
	 */
	public function advance(
		int $batch_id,
		int $expected_quantity,
		bool $complete,
		DateTimeImmutable $updated_at
	): bool {
		if (
			null === $this->state
			|| $this->state->batch_id !== $batch_id
			|| BatchStatus::GENERATING !== $this->state->batch_status
			|| $this->state->generated_quantity !== $expected_quantity
			|| $this->tag_count !== $expected_quantity
		) {
			return false;
		}

		$next = $expected_quantity + 1;

		if ( $next > $this->state->requested_quantity ) {
			return false;
		}

		$this->tag_count = $next;
		$this->state     = $this->replace(
			$complete ? BatchStatus::GENERATED : BatchStatus::GENERATING,
			$next,
			$updated_at
		);

		return true;
	}

	/**
	 * Complete a generating Batch already at its target.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param int               $expected_quantity Expected final quantity.
	 * @param DateTimeImmutable $updated_at UTC completion time.
	 */
	public function complete(
		int $batch_id,
		int $expected_quantity,
		DateTimeImmutable $updated_at
	): bool {
		if (
			null === $this->state
			|| $this->state->batch_id !== $batch_id
			|| BatchStatus::GENERATING !== $this->state->batch_status
			|| $this->state->generated_quantity !== $expected_quantity
			|| $this->state->requested_quantity !== $expected_quantity
		) {
			return false;
		}

		$this->state = $this->replace( BatchStatus::GENERATED, $expected_quantity, $updated_at );

		return true;
	}

	/**
	 * Rebuild the immutable state with changed progress fields.
	 *
	 * @param BatchStatus       $status New status.
	 * @param int               $quantity New committed quantity.
	 * @param DateTimeImmutable $updated_at UTC update time.
	 * @throws LogicException When the fixture has no stored Batch.
	 */
	private function replace(
		BatchStatus $status,
		int $quantity,
		DateTimeImmutable $updated_at
	): BatchGenerationState {
		if ( null === $this->state ) {
			throw new LogicException( 'The Batch generation fixture has no state.' );
		}

		return new BatchGenerationState(
			$this->state->batch_id,
			$this->state->tag_type,
			$this->state->model_code,
			$this->state->requested_quantity,
			$quantity,
			$status,
			$this->state->activation_enabled,
			$updated_at
		);
	}
}
