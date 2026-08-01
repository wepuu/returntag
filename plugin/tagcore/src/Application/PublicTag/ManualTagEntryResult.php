<?php
/**
 * Manual Tag entry result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\PublicTag;

use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Carries only a canonical Tag ID after successful public input validation.
 */
final readonly class ManualTagEntryResult {
	/**
	 * Create one bounded result.
	 *
	 * @param ManualTagEntryResultState $state Result state.
	 * @param TagId|null                $tag_id Canonical Tag ID on acceptance.
	 */
	private function __construct(
		public ManualTagEntryResultState $state,
		public ?TagId $tag_id = null
	) {
	}

	/**
	 * Create an accepted result.
	 *
	 * @param TagId $tag_id Canonical Tag ID.
	 */
	public static function accepted( TagId $tag_id ): self {
		return new self( ManualTagEntryResultState::ACCEPTED, $tag_id );
	}

	/**
	 * Create an invalid-input result.
	 */
	public static function invalid(): self {
		return new self( ManualTagEntryResultState::INVALID );
	}

	/**
	 * Create a throttled result.
	 */
	public static function throttled(): self {
		return new self( ManualTagEntryResultState::THROTTLED );
	}
}
