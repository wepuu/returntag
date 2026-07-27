<?php
/**
 * Batch generation persistence state.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use DateTimeImmutable;
use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;

/**
 * Narrow locked Batch projection used by background generation.
 */
final readonly class BatchGenerationState {
	/**
	 * Create the state.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param TagType           $tag_type Physical Tag type snapshot.
	 * @param string|null       $model_code Model code snapshot.
	 * @param int               $requested_quantity Target Tag quantity.
	 * @param int               $generated_quantity Committed Tag quantity.
	 * @param BatchStatus       $batch_status Current Batch state.
	 * @param bool              $activation_enabled Batch activation control.
	 * @param DateTimeImmutable $updated_at Last UTC update time.
	 * @throws InvalidArgumentException When the stored generation state is invalid.
	 */
	public function __construct(
		public int $batch_id,
		public TagType $tag_type,
		public ?string $model_code,
		public int $requested_quantity,
		public int $generated_quantity,
		public BatchStatus $batch_status,
		public bool $activation_enabled,
		public DateTimeImmutable $updated_at
	) {
		RecordValidator::positive_id( $this->batch_id, 'batch_id' );
		RecordValidator::nullable_ascii( $this->model_code, 191, 'model_code' );
		RecordValidator::positive_id( $this->requested_quantity, 'requested_quantity' );
		RecordValidator::unsigned_int( $this->generated_quantity, 'generated_quantity' );
		RecordValidator::utc( $this->updated_at, 'updated_at' );

		if ( $this->generated_quantity > $this->requested_quantity ) {
			throw new InvalidArgumentException( 'Batch generation state is invalid.' );
		}
	}
}
