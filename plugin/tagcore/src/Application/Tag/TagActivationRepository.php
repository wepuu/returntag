<?php
/**
 * Atomic Tag activation persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use DateTimeImmutable;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Attempts one first-owner conditional write.
 */
interface TagActivationRepository {
	/**
	 * Atomically claim one eligible Tag or classify the committed state.
	 *
	 * @param TagId             $tag_id Canonical public Tag ID.
	 * @param int               $owner_id Server-derived WordPress User ID.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function activate( TagId $tag_id, int $owner_id, DateTimeImmutable $now ): TagActivationResult;
}
