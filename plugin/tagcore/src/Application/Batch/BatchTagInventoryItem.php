<?php
/**
 * Narrow Batch Tag inventory item.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;

/**
 * Contains only manufacturing inventory fields approved for RT-206.
 */
final readonly class BatchTagInventoryItem {
	/**
	 * Create one inventory item.
	 *
	 * @param TagId             $tag_id Public Tag ID.
	 * @param TagStatus         $tag_status Persisted Tag status.
	 * @param DateTimeImmutable $created_at UTC generation time.
	 */
	public function __construct(
		public TagId $tag_id,
		public TagStatus $tag_status,
		public DateTimeImmutable $created_at
	) {
		RecordValidator::utc( $this->created_at, 'created_at' );
	}
}
