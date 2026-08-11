<?php
/**
 * Read current-Owner Conversation summaries.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;

/** Derives Owner identity from the WordPress session and fails closed. */
final readonly class ReadOwnerConversations {
	/**
	 * Create the summary use case.
	 *
	 * @param AuthenticatedSession    $session Server-derived WordPress identity.
	 * @param FeatureFlagReader       $feature_flags Operational controls.
	 * @param OwnerConversationReader $conversations Privacy-minimized reader.
	 * @param Clock                   $clock UTC clock.
	 */
	public function __construct(
		private AuthenticatedSession $session,
		private FeatureFlagReader $feature_flags,
		private OwnerConversationReader $conversations,
		private Clock $clock
	) {
	}

	/** Return the current Owner's privacy-minimized summaries. */
	public function execute(): OwnerConversationCollection {
		if ( ! $this->feature_flags->is_enabled( FeatureFlag::OWNER_ACCOUNT ) ) {
			return new OwnerConversationCollection( OwnerConversationAccessState::UNAVAILABLE );
		}

		$owner_id = $this->session->current_user_id();

		if ( null === $owner_id ) {
			return new OwnerConversationCollection( OwnerConversationAccessState::AUTHENTICATION_REQUIRED );
		}

		return new OwnerConversationCollection(
			OwnerConversationAccessState::READY,
			$this->conversations->list_for_owner( $owner_id, $this->clock->now() )
		);
	}
}
