<?php
/**
 * Manual Tag entry form state.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

enum ManualTagEntryFormState: string {
	case READY       = 'ready';
	case INVALID     = 'invalid';
	case FORBIDDEN   = 'forbidden';
	case THROTTLED   = 'throttled';
	case UNAVAILABLE = 'unavailable';
}
