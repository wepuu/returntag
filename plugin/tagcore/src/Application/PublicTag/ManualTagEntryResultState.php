<?php
/**
 * Manual Tag entry result state.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\PublicTag;

enum ManualTagEntryResultState: string {
	case ACCEPTED  = 'accepted';
	case INVALID   = 'invalid';
	case THROTTLED = 'throttled';
}
