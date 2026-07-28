<?php
/**
 * Locked Batch export state.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\SmartNetwork;
use ReturnTag\TagCore\Domain\Tag\TagType;

/**
 * Narrow state required to authorize and commit an export.
 */
final readonly class BatchExportState {
	/**
	 * Create a locked export state.
	 *
	 * @param int          $batch_id Batch identifier.
	 * @param string       $batch_code Canonical Batch Code.
	 * @param TagType      $tag_type Manufacturing type.
	 * @param string|null  $model_code Model code.
	 * @param SmartNetwork $smart_network Display-only network descriptor.
	 * @param int          $requested_quantity Requested quantity.
	 * @param int          $generated_quantity Committed generated quantity.
	 * @param BatchStatus  $batch_status Current lifecycle state.
	 */
	public function __construct(
		public int $batch_id,
		public string $batch_code,
		public TagType $tag_type,
		public ?string $model_code,
		public SmartNetwork $smart_network,
		public int $requested_quantity,
		public int $generated_quantity,
		public BatchStatus $batch_status
	) {
		RecordValidator::positive_id( $this->batch_id, 'batch_id' );
		RecordValidator::ascii( $this->batch_code, 191, 'batch_code' );
		RecordValidator::nullable_ascii( $this->model_code, 191, 'model_code' );
		RecordValidator::positive_id( $this->requested_quantity, 'requested_quantity' );
		RecordValidator::unsigned_int( $this->generated_quantity, 'generated_quantity' );
	}
}
