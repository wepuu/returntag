<?php
/**
 * Manual Tag entry presentation intent.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

/**
 * An untrusted presentation hint that never selects the final Tag workflow.
 */
enum TagEntryIntent: string {
	case ACTIVATE = 'activate';
	case REPORT   = 'report';
}
