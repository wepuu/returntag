<?php
/**
 * Public Tag page view model.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\PublicTag;

use ReturnTag\TagCore\Domain\Tag\TagType;

/**
 * Carries only fields approved for the selected public experience.
 */
final readonly class PublicTagPage {
	/**
	 * Create a page through one of the state-specific factories.
	 *
	 * @param PublicTagPageState $state Derived page state.
	 * @param TagType|null       $tag_type Optional public product type.
	 * @param string|null        $public_label Optional Finder-safe public label.
	 * @param bool               $lost_mode Whether the Finder callout is active.
	 * @param string|null        $lost_message Optional Finder-safe Lost Mode message.
	 */
	private function __construct(
		public PublicTagPageState $state,
		public ?TagType $tag_type = null,
		public ?string $public_label = null,
		public bool $lost_mode = false,
		public ?string $lost_message = null
	) {
	}

	/**
	 * Build the non-enumerating invalid-ID page.
	 */
	public static function invalid(): self {
		return new self( PublicTagPageState::INVALID );
	}

	/**
	 * Build the fail-closed infrastructure or data-integrity page.
	 */
	public static function service_unavailable(): self {
		return new self( PublicTagPageState::SERVICE_UNAVAILABLE );
	}

	/**
	 * Build the first-activation paused page.
	 *
	 * @param TagType $tag_type Public product type.
	 */
	public static function activation_unavailable( TagType $tag_type ): self {
		return new self( PublicTagPageState::ACTIVATION_UNAVAILABLE, $tag_type );
	}

	/**
	 * Build the first-activation entry page.
	 *
	 * @param TagType $tag_type Public product type.
	 */
	public static function activation_entry( TagType $tag_type ): self {
		return new self( PublicTagPageState::ACTIVATION_ENTRY, $tag_type );
	}

	/**
	 * Build the recognized-owner entry page.
	 *
	 * @param TagType $tag_type Public product type.
	 */
	public static function owner_entry( TagType $tag_type ): self {
		return new self( PublicTagPageState::OWNER_ENTRY, $tag_type );
	}

	/**
	 * Build the Finder entry page with only approved public fields.
	 *
	 * @param TagType     $tag_type Public product type.
	 * @param string|null $public_label Optional public label.
	 * @param bool        $lost_mode Independent Lost Mode state.
	 * @param string|null $lost_message Optional approved Lost Mode message.
	 */
	public static function finder_entry(
		TagType $tag_type,
		?string $public_label,
		bool $lost_mode,
		?string $lost_message
	): self {
		return new self(
			PublicTagPageState::FINDER_ENTRY,
			$tag_type,
			$public_label,
			$lost_mode,
			$lost_mode ? $lost_message : null
		);
	}

	/**
	 * Build the operationally paused Finder page.
	 *
	 * @param TagType $tag_type Public product type.
	 */
	public static function finder_unavailable( TagType $tag_type ): self {
		return new self( PublicTagPageState::FINDER_UNAVAILABLE, $tag_type );
	}

	/**
	 * Build the Tag-level suspension page.
	 */
	public static function suspended(): self {
		return new self( PublicTagPageState::SUSPENDED );
	}

	/**
	 * Build the permanently retired Tag page.
	 */
	public static function retired(): self {
		return new self( PublicTagPageState::RETIRED );
	}
}
