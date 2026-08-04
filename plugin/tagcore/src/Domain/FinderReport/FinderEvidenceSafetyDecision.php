<?php
/**
 * Finder evidence content-safety decision.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\FinderReport;

/**
 * Closed vocabulary returned by an approved content-safety provider.
 */
enum FinderEvidenceSafetyDecision: string {
	case APPROVED = 'approved';
	case REJECTED = 'rejected';
}
