<?php
/**
 * Fixed privacy action-required reasons.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\Privacy;

/** Prevents free-text explanations from entering the request ledger. */
enum PrivacyRequestReason: string {
	case ACTIVE_TAG     = 'active_tag';
	case RETENTION_HOLD = 'retention_hold';
}
