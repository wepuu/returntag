<?php
/**
 * Bounded Owner Tag mutation use cases.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Domain\Tag\TagId;

/** Rechecks session, control, rate limit, ownership, and active state for writes. */
final readonly class MutateOwnerTag {
	/**
	 * Create the Stage 2 mutation service.
	 *
	 * @param AuthenticatedSession        $session Authenticated WordPress session.
	 * @param FeatureFlagReader           $feature_flags Operational controls.
	 * @param OwnerTagMutationStore       $tags Atomic Tag mutation store.
	 * @param OwnerTagMutationRateLimiter $rate_limiter Mutation budget.
	 * @param EventRepository             $events Privacy-safe Event store.
	 * @param TransactionManager          $transactions Database transaction boundary.
	 * @param Clock                       $clock UTC clock.
	 */
	public function __construct(
		private AuthenticatedSession $session,
		private FeatureFlagReader $feature_flags,
		private OwnerTagMutationStore $tags,
		private OwnerTagMutationRateLimiter $rate_limiter,
		private EventRepository $events,
		private TransactionManager $transactions,
		private Clock $clock
	) {
	}

	/**
	 * Update private and public labels.
	 *
	 * @param TagId            $tag_id Selected public Tag identifier.
	 * @param OwnerTagMetadata $metadata Validated complete metadata.
	 */
	public function update_metadata( TagId $tag_id, OwnerTagMetadata $metadata ): OwnerTagMutationResult {
		return $this->execute(
			$tag_id,
			OwnerTagMutationEventIdentityPolicy::METADATA_UPDATED,
			fn( int $owner_id, \DateTimeImmutable $now ): OwnerTagMutationResult => $this->tags->update_metadata( $tag_id, $owner_id, $metadata, $now )
		);
	}

	/**
	 * Update Lost Mode and Finder-visible guidance.
	 *
	 * @param TagId             $tag_id Selected public Tag identifier.
	 * @param OwnerTagLostState $state Validated complete Lost Mode state.
	 */
	public function update_lost_state( TagId $tag_id, OwnerTagLostState $state ): OwnerTagMutationResult {
		return $this->execute(
			$tag_id,
			OwnerTagMutationEventIdentityPolicy::LOST_STATE_UPDATED,
			fn( int $owner_id, \DateTimeImmutable $now ): OwnerTagMutationResult => $this->tags->update_lost_state( $tag_id, $owner_id, $state, $now )
		);
	}

	/**
	 * Record one idempotent Smart Setup acknowledgement.
	 *
	 * @param TagId $tag_id Selected public Tag identifier.
	 */
	public function acknowledge_smart_setup( TagId $tag_id ): OwnerTagMutationResult {
		return $this->execute(
			$tag_id,
			OwnerTagMutationEventIdentityPolicy::SMART_SETUP_ACKNOWLEDGED,
			fn( int $owner_id, \DateTimeImmutable $now ): OwnerTagMutationResult => $this->tags->acknowledge_smart_setup( $tag_id, $owner_id, $now )
		);
	}

	/**
	 * Execute one closed mutation and append its fixed Event atomically.
	 *
	 * @param TagId                                                     $tag_id Selected public Tag identifier.
	 * @param string                                                    $event_type Fixed Event type.
	 * @param callable(int, \DateTimeImmutable): OwnerTagMutationResult $operation Current-Owner mutation.
	 */
	private function execute( TagId $tag_id, string $event_type, callable $operation ): OwnerTagMutationResult {
		if ( ! $this->feature_flags->is_enabled( FeatureFlag::OWNER_ACCOUNT ) ) {
			return OwnerTagMutationResult::UNAVAILABLE;
		}

		$owner_id = $this->session->current_user_id();

		if ( null === $owner_id ) {
			return OwnerTagMutationResult::AUTHENTICATION_REQUIRED;
		}

		$now = $this->clock->now();

		if ( ! $this->rate_limiter->reserve( $owner_id, $tag_id, $now ) ) {
			return OwnerTagMutationResult::THROTTLED;
		}

		return $this->transactions->transactional(
			function () use ( $tag_id, $event_type, $operation, $owner_id, $now ): OwnerTagMutationResult {
				if ( ! $this->feature_flags->is_enabled( FeatureFlag::OWNER_ACCOUNT ) ) {
					return OwnerTagMutationResult::UNAVAILABLE;
				}

				$result = $operation( $owner_id, $now );

				if ( OwnerTagMutationResult::UPDATED !== $result ) {
					return $result;
				}

				$this->events->append(
					new NewEventRecord(
						$event_type,
						'user',
						$owner_id,
						'tag',
						$tag_id->value,
						'success',
						null,
						EventMetadata::none(),
						$now
					)
				);

				return $result;
			}
		);
	}
}
