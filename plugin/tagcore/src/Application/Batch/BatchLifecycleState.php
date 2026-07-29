<?php
/**
 * Narrow persisted Batch lifecycle state.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;

/**
 * Contains only fields required to authorize lifecycle transitions.
 */
final readonly class BatchLifecycleState {
	/**
	 * Create one state snapshot.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param string            $batch_code Canonical Batch Code.
	 * @param int               $requested_quantity Requested manufacturing quantity.
	 * @param int               $generated_quantity Committed generated quantity.
	 * @param BatchStatus       $batch_status Current lifecycle status.
	 * @param bool              $activation_enabled Batch activation control.
	 * @param DateTimeImmutable $updated_at UTC update time.
	 */
	public function __construct(
		public int $batch_id,
		public string $batch_code,
		public int $requested_quantity,
		public int $generated_quantity,
		public BatchStatus $batch_status,
		public bool $activation_enabled,
		public DateTimeImmutable $updated_at
	) {
		RecordValidator::positive_id( $this->batch_id, 'batch_id' );
		RecordValidator::ascii( $this->batch_code, 191, 'batch_code' );
		RecordValidator::positive_id( $this->requested_quantity, 'requested_quantity' );
		RecordValidator::unsigned_int( $this->generated_quantity, 'generated_quantity' );
		RecordValidator::utc( $this->updated_at, 'updated_at' );
	}
}
