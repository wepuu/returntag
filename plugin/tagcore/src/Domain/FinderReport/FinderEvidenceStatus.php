<?php
/**
 * Finder evidence lifecycle states.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\FinderReport;

/**
 * Canonical private evidence-processing states.
 */
enum FinderEvidenceStatus: string {
	case QUARANTINED = 'quarantined';
	case PROCESSING  = 'processing';
	case READY       = 'ready';
	case REJECTED    = 'rejected';
	case DELETED     = 'deleted';
}
