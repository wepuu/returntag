<?php
/**
 * Create Batch input.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Tag\SmartNetwork;
use ReturnTag\TagCore\Domain\Tag\TagType;

/**
 * Immutable, validated input for creating one draft Batch.
 */
final readonly class CreateBatchInput {
	private const MAX_REQUESTED_QUANTITY = 4294967295;

	/**
	 * Create validated Batch input.
	 *
	 * @param string       $batch_code Batch code.
	 * @param TagType      $tag_type Physical Tag type.
	 * @param string|null  $model_code Model code.
	 * @param SmartNetwork $smart_network Display-only smart-network descriptor.
	 * @param int          $requested_quantity Requested Tag quantity.
	 * @param string|null  $manufacturer Manufacturer label.
	 * @param string|null  $sales_channel Sales-channel code.
	 * @param string|null  $notes Internal operator notes.
	 * @param int          $created_by Current WordPress User ID.
	 * @throws InvalidArgumentException When an input violates the Batch persistence contract.
	 */
	public function __construct(
		public string $batch_code,
		public TagType $tag_type,
		public ?string $model_code,
		public SmartNetwork $smart_network,
		public int $requested_quantity,
		public ?string $manufacturer,
		public ?string $sales_channel,
		public ?string $notes,
		public int $created_by
	) {
		RecordValidator::ascii( $this->batch_code, 191, 'batch_code' );

		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9-]{0,190}$/D', $this->batch_code ) ) {
			throw new InvalidArgumentException( 'Batch Code contains unsupported characters.' );
		}

		RecordValidator::nullable_ascii( $this->model_code, 191, 'model_code' );
		RecordValidator::positive_id( $this->requested_quantity, 'requested_quantity' );

		if ( $this->requested_quantity > self::MAX_REQUESTED_QUANTITY ) {
			throw new InvalidArgumentException( 'Requested quantity exceeds the storage limit.' );
		}

		RecordValidator::nullable_text( $this->manufacturer, 191, 'manufacturer' );
		RecordValidator::nullable_ascii( $this->sales_channel, 64, 'sales_channel' );
		RecordValidator::nullable_text_bytes( $this->notes, 5000, 'notes' );
		RecordValidator::positive_id( $this->created_by, 'created_by' );

		if ( TagType::SMART_TAG !== $this->tag_type && SmartNetwork::NONE !== $this->smart_network ) {
			throw new InvalidArgumentException( 'Smart Network applies only to Smart Tags.' );
		}
	}
}
