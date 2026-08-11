<?php
/**
 * Owner Tag mutation rate-limit port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use DateTimeImmutable;
use ReturnTag\TagCore\Domain\Tag\TagId;

/** Reserves a bounded current-Owner mutation budget. */
interface OwnerTagMutationRateLimiter {
	/**
	 * Reserve one mutation attempt.
	 *
	 * @param int               $owner_id Current Owner identifier.
	 * @param TagId             $tag_id Selected public Tag identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve( int $owner_id, TagId $tag_id, DateTimeImmutable $now ): bool;
}
