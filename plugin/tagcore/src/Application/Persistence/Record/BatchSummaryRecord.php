<?php
/**
 * Batch list projection.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;

/**
 * Narrow Batch projection that excludes notes and other wide fields.
 */
final readonly class BatchSummaryRecord {
	/**
	 * Create one Batch summary.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param string            $batch_code Batch code.
	 * @param TagType           $tag_type Physical Tag type.
	 * @param string|null       $model_code Model code.
	 * @param int               $requested_quantity Requested quantity.
	 * @param int               $generated_quantity Generated quantity.
	 * @param BatchStatus       $batch_status Persisted state.
	 * @param bool              $activation_enabled Activation control.
	 * @param DateTimeImmutable $created_at UTC creation time.
	 */
	public function __construct(
		public int $batch_id,
		public string $batch_code,
		public TagType $tag_type,
		public ?string $model_code,
		public int $requested_quantity,
		public int $generated_quantity,
		public BatchStatus $batch_status,
		public bool $activation_enabled,
		public DateTimeImmutable $created_at
	) {
		RecordValidator::positive_id( $this->batch_id, 'batch_id' );
		RecordValidator::ascii( $this->batch_code, 191, 'batch_code' );
		RecordValidator::nullable_ascii( $this->model_code, 191, 'model_code' );
		RecordValidator::positive_id( $this->requested_quantity, 'requested_quantity' );
		RecordValidator::unsigned_int( $this->generated_quantity, 'generated_quantity' );
		RecordValidator::utc( $this->created_at, 'created_at' );
	}
}
