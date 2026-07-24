<?php
/**
 * Smart-network descriptors.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\Tag;

/**
 * Display-only smart-network values.
 */
enum SmartNetwork: string {
	case NONE            = 'none';
	case APPLE_FIND_MY   = 'apple_find_my';
	case GOOGLE_FIND_HUB = 'google_find_hub';
	case OTHER           = 'other';
}
