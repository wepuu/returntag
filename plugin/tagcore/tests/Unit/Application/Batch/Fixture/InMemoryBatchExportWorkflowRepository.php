<?php
/**
 * In-memory Batch export workflow fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Batch\BatchExportState;
use ReturnTag\TagCore\Application\Batch\BatchExportWorkflowRepository;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;

/**
 * Records export state operations without a database.
 */
final class InMemoryBatchExportWorkflowRepository implements BatchExportWorkflowRepository {
	/**
	 * Number of exported-state writes.
	 *
	 * @var int
	 */
	public int $mark_exported_calls = 0;

	/**
	 * Create the fixture.
	 *
	 * @param BatchExportState|null $state Locked state.
	 * @param int                   $tag_count Stored Tag count.
	 */
	public function __construct(
		public ?BatchExportState $state,
		public int $tag_count
	) {
	}

	/**
	 * Return the fixed state.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function lock_by_id( int $batch_id ): ?BatchExportState {
		unset( $batch_id );

		return $this->state;
	}

	/**
	 * Return the fixed Tag count.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function count_tags( int $batch_id ): int {
		unset( $batch_id );

		return $this->tag_count;
	}

	/**
	 * Record and apply a generated-to-exported transition.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param DateTimeImmutable $updated_at UTC transition time.
	 */
	public function mark_exported( int $batch_id, DateTimeImmutable $updated_at ): bool {
		unset( $batch_id, $updated_at );
		++$this->mark_exported_calls;

		if ( null === $this->state || BatchStatus::GENERATED !== $this->state->batch_status ) {
			return false;
		}

		$this->state = new BatchExportState(
			$this->state->batch_id,
			$this->state->batch_code,
			$this->state->tag_type,
			$this->state->model_code,
			$this->state->smart_network,
			$this->state->requested_quantity,
			$this->state->generated_quantity,
			BatchStatus::EXPORTED
		);

		return true;
	}
}
