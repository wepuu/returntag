<?php
/**
 * Explicit Account-to-Secure-Reply continuation.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use DateInterval;
use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayProtector;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/** Issues no access unless current active ownership and relay eligibility hold. */
final readonly class ContinueOwnerConversation {
	/**
	 * Create the continuation service.
	 *
	 * @param AuthenticatedSession               $session Server-derived WordPress identity.
	 * @param FeatureFlagReader                  $feature_flags Operational controls.
	 * @param OwnerConversationContinuationStore $conversations Atomic persistence boundary.
	 * @param ConversationRelayProtector         $protector Existing relay token protection.
	 * @param Clock                              $clock UTC clock.
	 */
	public function __construct(
		private AuthenticatedSession $session,
		private FeatureFlagReader $feature_flags,
		private OwnerConversationContinuationStore $conversations,
		private ConversationRelayProtector $protector,
		private Clock $clock
	) {
	}

	/**
	 * Issue one role-bound 30-minute Owner session.
	 *
	 * @param int $conversation_id Browser-selected Conversation candidate.
	 */
	public function execute( int $conversation_id ): OwnerConversationContinuationResult {
		RecordValidator::positive_id( $conversation_id, 'conversation_id' );

		if ( ! $this->feature_flags->is_enabled( FeatureFlag::OWNER_ACCOUNT ) ) {
			return OwnerConversationContinuationResult::unavailable();
		}

		$owner_id = $this->session->current_user_id();

		if ( null === $owner_id ) {
			return OwnerConversationContinuationResult::unavailable();
		}

		$raw_session = $this->protector->generate_token();
		$now         = $this->clock->now();
		$issued      = $this->conversations->issue_owner_session(
			$conversation_id,
			$owner_id,
			$this->protector->token_digest( $raw_session ),
			$now->add( new DateInterval( 'PT30M' ) ),
			$now
		);

		return $issued
			? OwnerConversationContinuationResult::continued( $raw_session )
			: OwnerConversationContinuationResult::unavailable();
	}
}
