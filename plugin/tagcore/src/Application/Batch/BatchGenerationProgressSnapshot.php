<?php
/**
 * Stored Batch generation progress snapshot.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use DateTimeImmutable;
use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;

/**
 * Narrow immutable persistence projection used by the administrative query.
 */
final readonly class BatchGenerationProgressSnapshot {
	/**
	 * Create one stored progress snapshot.
	 *
	 * @param int                    $batch_id Batch identifier.
	 * @param int                    $requested_quantity Target quantity.
	 * @param int                    $generated_quantity Committed quantity.
	 * @param BatchStatus            $batch_status Current Batch state.
	 * @param bool                   $activation_enabled Batch activation control.
	 * @param DateTimeImmutable|null $started_at Audited generation start.
	 * @param DateTimeImmutable|null $completed_at Audited generation completion.
	 * @param DateTimeImmutable      $updated_at Last committed Batch update.
	 * @throws InvalidArgumentException When progress or audit timestamps are inconsistent.
	 */
	public function __construct(
		public int $batch_id,
		public int $requested_quantity,
		public int $generated_quantity,
		public BatchStatus $batch_status,
		public bool $activation_enabled,
		public ?DateTimeImmutable $started_at,
		public ?DateTimeImmutable $completed_at,
		public DateTimeImmutable $updated_at
	) {
		RecordValidator::positive_id( $this->batch_id, 'batch_id' );
		RecordValidator::positive_id( $this->requested_quantity, 'requested_quantity' );
		RecordValidator::unsigned_int( $this->generated_quantity, 'generated_quantity' );
		RecordValidator::nullable_utc( $this->started_at, 'started_at' );
		RecordValidator::nullable_utc( $this->completed_at, 'completed_at' );
		RecordValidator::utc( $this->updated_at, 'updated_at' );

		if (
			$this->generated_quantity > $this->requested_quantity
			|| ( null !== $this->completed_at && null === $this->started_at )
			|| (
				null !== $this->started_at
				&& null !== $this->completed_at
				&& $this->completed_at < $this->started_at
			)
		) {
			throw new InvalidArgumentException( 'Batch generation progress is invalid.' );
		}
	}
}
