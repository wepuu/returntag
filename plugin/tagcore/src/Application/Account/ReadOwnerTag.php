<?php
/**
 * Read one current-Owner Tag.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Resolves detail through a combined Tag-and-current-Owner predicate.
 */
final readonly class ReadOwnerTag {
	/**
	 * Create the current-Owner detail use case.
	 *
	 * @param AuthenticatedSession $session Server-side WordPress session.
	 * @param FeatureFlagReader    $feature_flags Operational controls.
	 * @param OwnerTagReader       $tags Current-Owner persistence reader.
	 */
	public function __construct(
		private AuthenticatedSession $session,
		private FeatureFlagReader $feature_flags,
		private OwnerTagReader $tags
	) {
	}

	/**
	 * Return one currently-owned Tag or a closed safe state.
	 *
	 * @param TagId $tag_id Canonical public Tag ID.
	 */
	public function execute( TagId $tag_id ): OwnerTagDetail {
		if ( ! $this->feature_flags->is_enabled( FeatureFlag::OWNER_ACCOUNT ) ) {
			return new OwnerTagDetail( OwnerTagAccessState::UNAVAILABLE );
		}

		$owner_id = $this->session->current_user_id();

		if ( null === $owner_id ) {
			return new OwnerTagDetail( OwnerTagAccessState::AUTHENTICATION_REQUIRED );
		}

		$tag = $this->tags->find_for_owner( $owner_id, $tag_id );

		return null === $tag
			? new OwnerTagDetail( OwnerTagAccessState::UNAVAILABLE )
			: new OwnerTagDetail( OwnerTagAccessState::READY, $tag );
	}
}
