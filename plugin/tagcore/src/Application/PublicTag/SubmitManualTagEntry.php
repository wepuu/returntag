<?php
/**
 * Manual Tag entry use case.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\PublicTag;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Tag\TagIdInputNormalizer;

/**
 * Reserves abuse budgets before normalizing one public Tag ID.
 */
final readonly class SubmitManualTagEntry {
	/**
	 * Create the use case.
	 *
	 * @param TagIdInputNormalizer      $tag_ids Canonical Tag ID boundary.
	 * @param ManualTagEntryRateLimiter $rate_limiter Durable public limiter.
	 * @param Clock                     $clock UTC clock.
	 */
	public function __construct(
		private TagIdInputNormalizer $tag_ids,
		private ManualTagEntryRateLimiter $rate_limiter,
		private Clock $clock
	) {
	}

	/**
	 * Reserve capacity and normalize one public input.
	 *
	 * @param string       $raw_tag_id Bounded raw Tag ID.
	 * @param LookupDigest $ip_lookup Keyed direct-peer lookup.
	 */
	public function execute( string $raw_tag_id, LookupDigest $ip_lookup ): ManualTagEntryResult {
		if ( ! $this->rate_limiter->reserve( $ip_lookup, $this->clock->now() ) ) {
			return ManualTagEntryResult::throttled();
		}

		try {
			return ManualTagEntryResult::accepted( $this->tag_ids->normalize( $raw_tag_id ) );
		} catch ( InvalidArgumentException ) {
			return ManualTagEntryResult::invalid();
		}
	}
}
