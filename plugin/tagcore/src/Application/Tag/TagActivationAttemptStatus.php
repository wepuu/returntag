<?php
/**
 * Authenticated Tag activation-attempt status.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

/**
 * Distinguishes a resolved attempt from generic throttling feedback.
 */
enum TagActivationAttemptStatus: string {
	case RESOLVED  = 'resolved';
	case THROTTLED = 'throttled';
}
