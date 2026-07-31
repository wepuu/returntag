<?php
/**
 * First-activation outcomes.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

/**
 * Keeps persistence races separate from public page states.
 */
enum TagActivationResult: string {
	case ACTIVATED     = 'activated';
	case ALREADY_OWNED = 'already_owned';
	case STATE_CHANGED = 'state_changed';
	case UNAVAILABLE   = 'unavailable';
}
