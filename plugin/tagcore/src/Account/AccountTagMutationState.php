<?php
/**
 * Owner Tag form feedback states.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

/** Closed, privacy-safe Stage 2 presentation states. */
enum AccountTagMutationState {
	case NONE;
	case UPDATED;
	case UNCHANGED;
	case SMART_SETUP_ACKNOWLEDGED;
	case INVALID_METADATA;
	case INVALID_LOST_MESSAGE;
	case THROTTLED;
	case UNAVAILABLE;
}
