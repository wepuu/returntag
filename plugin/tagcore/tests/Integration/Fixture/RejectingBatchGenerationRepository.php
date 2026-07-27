<?php
/**
 * Rejecting Batch generation Repository fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration\Fixture;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Batch\BatchGenerationState;
use ReturnTag\TagCore\Application\Persistence\Repository\BatchGenerationRepository;

/**
 * Exposes valid locked state but rejects every progress update.
 */
final readonly class RejectingBatchGenerationRepository implements BatchGenerationRepository {
	/**
	 * Create the fixture.
	 *
	 * @param BatchGenerationState $state Locked state.
	 */
	public function __construct( private BatchGenerationState $state ) {
	}

	/**
	 * Return the configured state.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function lock_by_id( int $batch_id ): ?BatchGenerationState {
		return $this->state->batch_id === $batch_id ? $this->state : null;
	}

	/**
	 * Return the zero checkpoint.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function count_tags( int $batch_id ): int {
		unset( $batch_id );

		return 0;
	}

	/**
	 * Reject start transitions.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param DateTimeImmutable $updated_at UTC transition time.
	 */
	public function mark_generating( int $batch_id, DateTimeImmutable $updated_at ): bool {
		unset( $batch_id, $updated_at );

		return false;
	}

	/**
	 * Reject counter advancement.
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
		unset( $batch_id, $expected_quantity, $complete, $updated_at );

		return false;
	}

	/**
	 * Reject completion transitions.
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
		unset( $batch_id, $expected_quantity, $updated_at );

		return false;
	}
}
