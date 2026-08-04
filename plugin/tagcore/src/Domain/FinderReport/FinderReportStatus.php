<?php
/**
 * Finder Report lifecycle states.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\FinderReport;

/**
 * Canonical one-way Finder Report states, separate from Conversation states.
 */
enum FinderReportStatus: string {
	case RECEIVED   = 'received';
	case PROCESSING = 'processing';
	case READY      = 'ready';
	case NOTIFIED   = 'notified';
	case BLOCKED    = 'blocked';
	case EXPIRED    = 'expired';
}
