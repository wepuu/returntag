<?php
/**
 * Resolve a public Tag page.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\PublicTag;

use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Coordinates one exact state read with server-owned operational controls.
 */
final readonly class ResolvePublicTagPage {
	/**
	 * Create the use case.
	 *
	 * @param PublicTagStateReader $states Public state reader.
	 * @param FeatureFlagReader    $feature_flags Site-scoped controls.
	 * @param PublicTagPagePolicy  $policy Pure state policy.
	 */
	public function __construct(
		private PublicTagStateReader $states,
		private FeatureFlagReader $feature_flags,
		private PublicTagPagePolicy $policy
	) {
	}

	/**
	 * Resolve one canonical public Tag page.
	 *
	 * @param TagId    $tag_id Canonical public Tag ID.
	 * @param int|null $current_user_id Server-derived WordPress user ID.
	 */
	public function execute( TagId $tag_id, ?int $current_user_id ): PublicTagPage {
		$record = $this->states->find( $tag_id );

		if ( null === $record ) {
			return PublicTagPage::invalid();
		}

		return $this->policy->decide(
			$record,
			null !== $current_user_id && $current_user_id > 0 ? $current_user_id : null,
			$this->feature_flags->is_enabled( FeatureFlag::GLOBAL_ACTIVATION ),
			$this->feature_flags->is_enabled( FeatureFlag::FINDER_CONTACT )
		);
	}
}
