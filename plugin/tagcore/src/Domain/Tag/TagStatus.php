<?php
/**
 * ReturnTag lifecycle values.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\Tag;

/**
 * Canonical persisted Tag states.
 */
enum TagStatus: string {
	case UNREGISTERED = 'unregistered';
	case ACTIVE       = 'active';
	case SUSPENDED    = 'suspended';
	case RETIRED      = 'retired';
}
