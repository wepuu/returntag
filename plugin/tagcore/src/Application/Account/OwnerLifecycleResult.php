<?php
/**
 * Owner lifecycle operation outcomes.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

/** Closed privacy-safe Transfer and Retire outcomes. */
enum OwnerLifecycleResult {
	case CREATED;
	case ACCEPTED;
	case CANCELLED;
	case RETIRED;
	case UNCHANGED;
	case AUTHENTICATION_REQUIRED;
	case THROTTLED;
	case UNAVAILABLE;
}
