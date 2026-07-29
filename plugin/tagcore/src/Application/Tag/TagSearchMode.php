<?php
/**
 * Administrative Tag search modes.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

/**
 * Requires one explicit search anchor and prevents unfiltered browsing.
 */
enum TagSearchMode: string {
	case TAG_ID = 'tag_id';
	case BATCH  = 'batch';
}
