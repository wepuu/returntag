<?php
/**
 * Owner Test Email outcomes.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

/** Closed privacy-safe request outcomes. */
enum OwnerTestEmailResult {
	case ACCEPTED;
	case THROTTLED;
	case UNAVAILABLE;
}
