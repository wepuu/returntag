<?php
/**
 * Narrow Batch export Tag projection.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagType;

/**
 * Contains only fields approved for the manufacturing CSV.
 */
final readonly class BatchExportSourceTag {
	/**
	 * Create an export Tag projection.
	 *
	 * @param TagId       $tag_id Public Tag ID.
	 * @param TagType     $tag_type Immutable type snapshot.
	 * @param string|null $model_code Immutable model snapshot.
	 */
	public function __construct(
		public TagId $tag_id,
		public TagType $tag_type,
		public ?string $model_code
	) {
		RecordValidator::nullable_ascii( $this->model_code, 191, 'model_code' );
	}
}
