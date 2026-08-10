<?php
/**
 * Submit one bounded private relay Message.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Application\Conversation;

use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;

/** Validates, encrypts, persists, and asynchronously schedules a human Message. */
final readonly class SubmitConversationMessage {
	/**
	 * Create the submission service.
	 *
	 * @param FeatureFlagReader              $flags Flags.
	 * @param ConversationRelayStore         $store Store.
	 * @param ConversationRelayProtector     $protector Protector.
	 * @param ConversationMessageRateLimiter $limiter Limiter.
	 * @param ConversationRelayScheduler     $scheduler Scheduler.
	 * @param Clock                          $clock UTC clock.
	 */
	public function __construct(
		private FeatureFlagReader $flags,
		private ConversationRelayStore $store,
		private ConversationRelayProtector $protector,
		private ConversationMessageRateLimiter $limiter,
		private ConversationRelayScheduler $scheduler,
		private Clock $clock
	) {}

	/**
	 * Validate, encrypt, persist, and schedule one Message.
	 *
	 * @param string $raw_session Raw session Token.
	 * @param string $value Untrusted body.
	 * @param string $peer_ip Direct peer IP.
	 */
	public function execute( string $raw_session, string $value, string $peer_ip ): bool {
		if ( ! $this->flags->is_enabled( FeatureFlag::FINDER_CONTACT ) || ! $this->flags->is_enabled( FeatureFlag::EMAIL_DISPATCH ) ) {
			return false; }
		$message = trim( str_replace( array( "\r\n", "\r" ), "\n", $value ) );
		$length  = function_exists( 'mb_strlen' ) ? mb_strlen( $message, 'UTF-8' ) : preg_match_all( '/./us', $message );
		if ( ! is_int( $length ) || $length < 10 || $length > 500 || preg_match( '/<\s*\/?\s*[a-z][^>]*>/iu', $message ) ) {
			return false; }
		try {
			$now            = $this->clock->now();
			$session_digest = $this->protector->token_digest( $raw_session );
			$session_lookup = \ReturnTag\TagCore\Application\Persistence\Value\LookupDigest::from_digest( $session_digest->value );
			$identity       = $this->store->resolve_session( $session_digest, $now );
			if ( null === $identity || ! $this->limiter->reserve( $session_lookup, $this->protector->peer_digest( $peer_ip ), $identity->conversation_id, $now ) ) {
				return false; }
			$record = $this->store->append_human_message( $identity, $this->protector->encrypt_message( $message, $identity->conversation_id, $identity->role ), $now );
			if ( null === $record ) {
				return false; }
			try {
				$this->scheduler->schedule( $record->message_id );
			} catch ( \Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Bounded recovery schedules unclaimed work.
				// Recovery schedules it.
			}
				return true;
		} catch ( \Throwable ) {
			return false; }
	}
}
