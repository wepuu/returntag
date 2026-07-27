<?php
/**
 * Generated Tag insertion input.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Tag\TagType;

/**
 * Trusted Batch snapshot used to persist one generated Tag.
 */
final readonly class GeneratedTagInput {
	/**
	 * Create generated Tag input.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param TagType           $tag_type Physical Tag type snapshot.
	 * @param string|null       $model_code Model code snapshot.
	 * @param DateTimeImmutable $created_at UTC generation time.
	 */
	public function __construct(
		public int $batch_id,
		public TagType $tag_type,
		public ?string $model_code,
		public DateTimeImmutable $created_at
	) {
		RecordValidator::positive_id( $this->batch_id, 'batch_id' );
		RecordValidator::nullable_ascii( $this->model_code, 191, 'model_code' );
		RecordValidator::utc( $this->created_at, 'created_at' );
	}
}
