<?php
/**
 * Single-use link exchange.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Application\Conversation;

use DateInterval;
use ReturnTag\TagCore\Application\Clock;

/** Exchanges a role-bound email link for a rotated 30-minute session. */
final readonly class ExchangeConversationLink {
	/**
	 * Create the link exchange service.
	 *
	 * @param ConversationRelayStore     $store Store.
	 * @param ConversationRelayProtector $protector Protector.
	 * @param Clock                      $clock UTC clock.
	 */
	public function __construct( private ConversationRelayStore $store, private ConversationRelayProtector $protector, private Clock $clock ) {}

	/**
	 * Exchange one raw email link for a raw short session.
	 *
	 * @param string $raw_link Raw link Token.
	 */
	public function execute( string $raw_link ): ?string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{43}$/D', $raw_link ) ) {
			return null; }
		$session  = $this->protector->generate_token();
		$now      = $this->clock->now();
		$identity = $this->store->exchange_link(
			$this->protector->token_digest( $raw_link ),
			$this->protector->token_digest( $session ),
			$now,
			$now->add( new DateInterval( 'PT30M' ) )
		);
		return null === $identity ? null : $session;
	}
}
