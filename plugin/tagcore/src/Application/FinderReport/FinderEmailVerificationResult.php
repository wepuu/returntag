<?php
/**
 * Finder email verification outcomes.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

enum FinderEmailVerificationResult: string {
	case ACCEPTED    = 'accepted';
	case VERIFIED    = 'verified';
	case INVALID     = 'invalid';
	case UNAVAILABLE = 'unavailable';
}
