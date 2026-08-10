<?php
/**
 * Finder Report idempotency claim outcomes.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

/** Distinguishes a new claim, completed replay, and invalid/in-flight input. */
enum FinderReportSubmissionClaim: string {
	case CLAIMED  = 'claimed';
	case REPLAYED = 'replayed';
	case INVALID  = 'invalid';
}
