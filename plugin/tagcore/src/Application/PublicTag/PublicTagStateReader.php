<?php
/**
 * Public Tag state read contract.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\PublicTag;

use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Loads one exact, privacy-minimized public Tag projection.
 */
interface PublicTagStateReader {
	/**
	 * Find one Tag and its Batch state by canonical public ID.
	 *
	 * @param TagId $tag_id Canonical public Tag ID.
	 */
	public function find( TagId $tag_id ): ?PublicTagStateRecord;
}
