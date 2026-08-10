<?php
/**
 * Finder Report abuse-budget port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Domain\Tag\TagId;

/** Reserves all anonymous report budgets atomically. */
interface FinderReportRateLimiter {
	/**
	 * Reserve per-Tag, peer, risk-signal, and global budgets.
	 *
	 * @param TagId             $tag_id Server-resolved Tag.
	 * @param LookupDigest      $peer_lookup Keyed peer lookup.
	 * @param LookupDigest      $risk_lookup Keyed risk lookup.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve(
		TagId $tag_id,
		LookupDigest $peer_lookup,
		LookupDigest $risk_lookup,
		DateTimeImmutable $now
	): bool;
}
