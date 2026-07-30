<?php
/**
 * Narrow public Tag state projection.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\PublicTag;

use DateTimeImmutable;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;

/**
 * Holds only persisted facts needed to derive a public page.
 */
final readonly class PublicTagStateRecord {
	/**
	 * Create the narrow state projection.
	 *
	 * @param int|null               $owner_id Internal WordPress owner identity.
	 * @param TagType                $tag_type Canonical product type.
	 * @param string|null            $public_label Finder-safe public label.
	 * @param TagStatus              $tag_status Canonical Tag state.
	 * @param bool                   $lost_mode Independent Lost Mode state.
	 * @param string|null            $lost_message Finder-safe Lost Mode message.
	 * @param DateTimeImmutable|null $activated_at Optional activation timestamp.
	 * @param BatchStatus|null       $batch_status Canonical Batch state, or null when its row is missing.
	 * @param bool|null              $batch_activation_enabled Batch activation control, or null when its row is missing.
	 */
	public function __construct(
		public ?int $owner_id,
		public TagType $tag_type,
		public ?string $public_label,
		public TagStatus $tag_status,
		public bool $lost_mode,
		public ?string $lost_message,
		public ?DateTimeImmutable $activated_at,
		public ?BatchStatus $batch_status,
		public ?bool $batch_activation_enabled
	) {
	}
}
