<?php
/**
 * Privacy-minimized administrative Tag projection.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;

/**
 * Contains only fields approved for RT-209 read-only administration.
 */
final readonly class TagSearchItem {
	/**
	 * Create one approved Tag projection.
	 *
	 * @param TagId                  $tag_id Public Tag ID.
	 * @param int                    $batch_id Internal Batch identifier.
	 * @param string                 $batch_code Manufacturing Batch Code.
	 * @param BatchStatus            $batch_status Canonical Batch status.
	 * @param bool                   $batch_activation_enabled Batch activation control.
	 * @param TagType                $tag_type Canonical physical product type.
	 * @param string|null            $model_code Optional model code.
	 * @param TagStatus              $tag_status Canonical Tag status.
	 * @param bool                   $lost_mode Whether Lost Mode is enabled.
	 * @param DateTimeImmutable|null $activated_at Optional activation time.
	 * @param DateTimeImmutable      $created_at UTC creation time.
	 * @param DateTimeImmutable      $updated_at UTC update time.
	 */
	public function __construct(
		public TagId $tag_id,
		public int $batch_id,
		public string $batch_code,
		public BatchStatus $batch_status,
		public bool $batch_activation_enabled,
		public TagType $tag_type,
		public ?string $model_code,
		public TagStatus $tag_status,
		public bool $lost_mode,
		public ?DateTimeImmutable $activated_at,
		public DateTimeImmutable $created_at,
		public DateTimeImmutable $updated_at
	) {
		RecordValidator::positive_id( $this->batch_id, 'batch_id' );
		RecordValidator::ascii( $this->batch_code, 191, 'batch_code' );
		RecordValidator::nullable_ascii( $this->model_code, 191, 'model_code' );
		RecordValidator::nullable_utc( $this->activated_at, 'activated_at' );
		RecordValidator::utc( $this->created_at, 'created_at' );
		RecordValidator::utc( $this->updated_at, 'updated_at' );
	}
}
