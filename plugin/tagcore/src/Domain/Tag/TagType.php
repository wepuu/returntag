<?php
/**
 * ReturnTag physical product types.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\Tag;

/**
 * Canonical persisted Tag types.
 */
enum TagType: string {
	case STICKER     = 'sticker';
	case CLASSIC_TAG = 'classic_tag';
	case SMART_TAG   = 'smart_tag';
}
