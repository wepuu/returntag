<?php
/**
 * Read the current Owner's Tags.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Pagination\TagCursor;

/**
 * Derives Owner identity only from the authenticated server-side session.
 */
final readonly class ReadOwnerTags {
	/**
	 * Create the current-Owner list use case.
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
	 * Return a bounded Tag page or a closed safe state.
	 *
	 * @param TagCursor|null $cursor Optional stable pagination cursor.
	 */
	public function execute( ?TagCursor $cursor = null ): OwnerTagCollection {
		if ( ! $this->feature_flags->is_enabled( FeatureFlag::OWNER_ACCOUNT ) ) {
			return new OwnerTagCollection( OwnerTagAccessState::UNAVAILABLE );
		}

		$owner_id = $this->session->current_user_id();

		if ( null === $owner_id ) {
			return new OwnerTagCollection( OwnerTagAccessState::AUTHENTICATION_REQUIRED );
		}

		return new OwnerTagCollection(
			OwnerTagAccessState::READY,
			$this->tags->list_for_owner( $owner_id, $cursor, new PageSize() )
		);
	}
}
