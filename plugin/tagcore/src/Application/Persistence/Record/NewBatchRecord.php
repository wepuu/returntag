<?php
/**
 * New Batch persistence data.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\SmartNetwork;
use ReturnTag\TagCore\Domain\Tag\TagType;

/**
 * Immutable data required to insert a Batch.
 */
final readonly class NewBatchRecord {
	/**
	 * Create Batch persistence data.
	 *
	 * @param string            $batch_code Batch code.
	 * @param TagType           $tag_type Physical Tag type.
	 * @param string|null       $model_code Model code.
	 * @param SmartNetwork      $smart_network Display-only network descriptor.
	 * @param string|null       $manufacturer Manufacturer label.
	 * @param string|null       $sales_channel Sales-channel code.
	 * @param int               $requested_quantity Requested quantity.
	 * @param int               $generated_quantity Generated quantity.
	 * @param BatchStatus       $batch_status Persisted Batch state.
	 * @param bool              $activation_enabled Activation control.
	 * @param string|null       $notes Operator notes.
	 * @param int               $created_by WordPress User ID.
	 * @param DateTimeImmutable $created_at UTC creation time.
	 * @param DateTimeImmutable $updated_at UTC update time.
	 */
	public function __construct(
		public string $batch_code,
		public TagType $tag_type,
		public ?string $model_code,
		public SmartNetwork $smart_network,
		public ?string $manufacturer,
		public ?string $sales_channel,
		public int $requested_quantity,
		public int $generated_quantity,
		public BatchStatus $batch_status,
		public bool $activation_enabled,
		public ?string $notes,
		public int $created_by,
		public DateTimeImmutable $created_at,
		public DateTimeImmutable $updated_at
	) {
		RecordValidator::ascii( $this->batch_code, 191, 'batch_code' );
		RecordValidator::nullable_ascii( $this->model_code, 191, 'model_code' );
		RecordValidator::nullable_text( $this->manufacturer, 191, 'manufacturer' );
		RecordValidator::nullable_ascii( $this->sales_channel, 64, 'sales_channel' );
		RecordValidator::positive_id( $this->requested_quantity, 'requested_quantity' );
		RecordValidator::unsigned_int( $this->generated_quantity, 'generated_quantity' );
		RecordValidator::nullable_text_bytes( $this->notes, 65535, 'notes' );
		RecordValidator::positive_id( $this->created_by, 'created_by' );
		RecordValidator::utc( $this->created_at, 'created_at' );
		RecordValidator::utc( $this->updated_at, 'updated_at' );
	}
}
