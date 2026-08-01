<?php
/**
 * Manual Tag entry submission result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Carries presentation state and an optional canonical redirect target.
 */
final readonly class ManualTagEntrySubmission {
	/**
	 * Create one browser-boundary result.
	 *
	 * @param ManualTagEntryFormState $state Safe presentation state.
	 * @param TagId|null              $tag_id Canonical Tag ID on success.
	 */
	public function __construct(
		public ManualTagEntryFormState $state,
		public ?TagId $tag_id = null
	) {
	}
}
