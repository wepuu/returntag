<?php
/**
 * Apply one authenticated participant Conversation safety action.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Conversation;

use ReturnTag\TagCore\Application\Clock;

/** Resolves the session and delegates the atomic terminal transition. */
final readonly class ApplyConversationSafetyAction {
	/**
	 * Create the service.
	 *
	 * @param ConversationRelayStore     $store Store.
	 * @param ConversationRelayProtector $protector Protector.
	 * @param Clock                      $clock UTC clock.
	 */
	public function __construct( private ConversationRelayStore $store, private ConversationRelayProtector $protector, private Clock $clock ) {}

	/**
	 * Apply one role-specific action from a valid session.
	 *
	 * @param string                   $raw_session Raw session Token.
	 * @param ConversationSafetyAction $action Closed action.
	 */
	public function execute( string $raw_session, ConversationSafetyAction $action ): bool {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{43}$/D', $raw_session ) ) {
			return false;
		}
		try {
			$now      = $this->clock->now();
			$identity = $this->store->resolve_session( $this->protector->token_digest( $raw_session ), $now );
			return null !== $identity && $identity->role === $action->role() && $this->store->apply_safety_action( $identity, $action, $now );
		} catch ( \Throwable ) {
			return false;
		}
	}
}
