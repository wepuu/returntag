<?php
/**
 * Public Tag page states.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\PublicTag;

/**
 * Identifies one server-derived, non-persisted public page state.
 */
enum PublicTagPageState: string {
	case INVALID                = 'invalid';
	case SERVICE_UNAVAILABLE    = 'service_unavailable';
	case ACTIVATION_UNAVAILABLE = 'activation_unavailable';
	case ACTIVATION_ENTRY       = 'activation_entry';
	case OWNER_ENTRY            = 'owner_entry';
	case FINDER_ENTRY           = 'finder_entry';
	case FINDER_UNAVAILABLE     = 'finder_unavailable';
	case SUSPENDED              = 'suspended';
	case RETIRED                = 'retired';
}
