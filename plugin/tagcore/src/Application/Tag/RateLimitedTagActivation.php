<?php
/**
 * Rate-limited authenticated Tag activation use case.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPageState;
use ReturnTag\TagCore\Application\PublicTag\ResolvePublicTagPage;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Reserves durable attempt budgets before invoking the atomic mutation.
 */
final readonly class RateLimitedTagActivation {
	/**
	 * Create the rate-limited activation use case.
	 *
	 * @param TagActivationRateLimiter  $rate_limiter Durable attempt budgets.
	 * @param ActivateTagAndResolvePage $activation Atomic activation and convergence.
	 * @param ResolvePublicTagPage      $pages Current committed public state.
	 * @param Clock                     $clock UTC clock.
	 */
	public function __construct(
		private TagActivationRateLimiter $rate_limiter,
		private ActivateTagAndResolvePage $activation,
		private ResolvePublicTagPage $pages,
		private Clock $clock
	) {
	}

	/**
	 * Resolve eligibility, reserve every budget, and attempt activation.
	 *
	 * @param TagId        $tag_id Canonical public Tag ID.
	 * @param int          $owner_id Server-derived WordPress User ID.
	 * @param LookupDigest $email_lookup Keyed email digest.
	 * @param LookupDigest $ip_lookup Keyed direct-peer IP digest.
	 */
	public function execute(
		TagId $tag_id,
		int $owner_id,
		LookupDigest $email_lookup,
		LookupDigest $ip_lookup
	): TagActivationAttemptResult {
		$current = $this->pages->execute( $tag_id, $owner_id );

		if ( PublicTagPageState::ACTIVATION_ENTRY !== $current->state ) {
			return new TagActivationAttemptResult( TagActivationAttemptStatus::RESOLVED, $current );
		}

		if ( ! $this->rate_limiter->reserve( $owner_id, $email_lookup, $ip_lookup, $tag_id, $this->clock->now() ) ) {
			return new TagActivationAttemptResult(
				TagActivationAttemptStatus::THROTTLED,
				$this->pages->execute( $tag_id, $owner_id )
			);
		}

		return new TagActivationAttemptResult(
			TagActivationAttemptStatus::RESOLVED,
			$this->activation->execute( $tag_id, $owner_id )
		);
	}
}
