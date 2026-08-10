<?php
/**
 * Finder Report Owner notification result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

/** Privacy-safe Worker outcomes. */
enum FinderReportOwnerNotificationResult: string {
	case NO_ACTION = 'no_action';
	case SENT      = 'sent';
	case FAILED    = 'failed';
}
