<?php
/**
 * Atomic first-owner activation use case.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Coordinates one conditional ownership write and its audit Event.
 */
final readonly class ActivateTag {
	/**
	 * Create the use case.
	 *
	 * @param TagActivationRepository $tags Atomic activation persistence.
	 * @param EventRepository         $events Privacy-safe Event persistence.
	 * @param TransactionManager      $transactions Database transaction boundary.
	 * @param FeatureFlagReader       $feature_flags Global incident controls.
	 * @param Clock                   $clock UTC clock.
	 */
	public function __construct(
		private TagActivationRepository $tags,
		private EventRepository $events,
		private TransactionManager $transactions,
		private FeatureFlagReader $feature_flags,
		private Clock $clock
	) {
	}

	/**
	 * Activate one Tag for the server-derived current WordPress user.
	 *
	 * @param TagId $tag_id Canonical public Tag ID.
	 * @param int   $owner_id Server-derived WordPress User ID.
	 * @throws InvalidArgumentException When the owner identifier is invalid.
	 */
	public function execute( TagId $tag_id, int $owner_id ): TagActivationResult {
		if ( $owner_id < 1 ) {
			throw new InvalidArgumentException( 'Activation owner is invalid.' );
		}

		if ( ! $this->feature_flags->is_enabled( FeatureFlag::GLOBAL_ACTIVATION ) ) {
			return TagActivationResult::UNAVAILABLE;
		}

		return $this->transactions->transactional(
			function () use ( $tag_id, $owner_id ): TagActivationResult {
				if ( ! $this->feature_flags->is_enabled( FeatureFlag::GLOBAL_ACTIVATION ) ) {
					return TagActivationResult::UNAVAILABLE;
				}

				$now    = $this->clock->now();
				$result = $this->tags->activate( $tag_id, $owner_id, $now );

				if ( TagActivationResult::ACTIVATED !== $result ) {
					return $result;
				}

				$this->events->append(
					new NewEventRecord(
						TagActivationEventIdentityPolicy::TAG_ACTIVATED,
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
