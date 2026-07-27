<?php
/**
 * Tag ID generator contract.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Generates one canonical candidate Tag ID.
 */
interface TagIdGenerator {
	/**
	 * Generate one candidate without persistence or collision handling.
	 */
	public function generate(): TagId;
}
