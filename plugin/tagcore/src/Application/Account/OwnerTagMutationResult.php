<?php
/**
 * Closed Owner Tag mutation outcomes.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

/** Prevents persistence detail from crossing the Account boundary. */
enum OwnerTagMutationResult {
	case UPDATED;
	case UNCHANGED;
	case AUTHENTICATION_REQUIRED;
	case UNAVAILABLE;
	case THROTTLED;
}
