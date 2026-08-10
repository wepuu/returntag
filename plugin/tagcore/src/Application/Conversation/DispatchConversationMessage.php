<?php
/**
 * Dispatch one encrypted Conversation Message.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Application\Conversation;

use DateInterval;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\FinderReport\FinderEmailProtector;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;

/** Claims once, resolves the opposite party, sends, and converges terminally. */
final readonly class DispatchConversationMessage {
	/**
	 * Create the dispatch service.
	 *
	 * @param FeatureFlagReader              $flags Flags.
	 * @param ConversationRelayStore         $store Store.
	 * @param ConversationRelayProtector     $protector Protector.
	 * @param FinderEmailProtector           $finder_emails Finder email protector.
	 * @param ConversationRelayOwnerResolver $owners Owner resolver.
	 * @param ConversationRelayEmailSender   $sender Sender.
	 * @param ConversationRelayLinkBuilder   $links Link builder.
	 * @param Clock                          $clock UTC clock.
	 */
	public function __construct(
		private FeatureFlagReader $flags,
		private ConversationRelayStore $store,
		private ConversationRelayProtector $protector,
		private FinderEmailProtector $finder_emails,
		private ConversationRelayOwnerResolver $owners,
		private ConversationRelayEmailSender $sender,
		private ConversationRelayLinkBuilder $links,
		private Clock $clock
	) {}

	/**
	 * Dispatch one queued Message exactly once.
	 *
	 * @param int $message_id Message identifier.
	 */
	public function execute( int $message_id ): bool {
		if ( $message_id < 1 || ! $this->flags->is_enabled( FeatureFlag::FINDER_CONTACT ) || ! $this->flags->is_enabled( FeatureFlag::EMAIL_DISPATCH ) ) {
			return false; }
		$now      = $this->clock->now();
		$dispatch = $this->store->claim_dispatch( $message_id, $now );
		if ( null === $dispatch ) {
			return false; }
		$link = null;
		try {
			$source         = $dispatch->message->data->sender_role;
			$recipient_role = MessageSenderRole::OWNER === $source ? MessageSenderRole::FINDER : MessageSenderRole::OWNER;
			$recipient      = MessageSenderRole::OWNER === $recipient_role
				? $this->owners->resolve( $dispatch->current_owner_id )
				: $this->finder_emails->decrypt_email( $dispatch->conversation->data->finder_email_ciphertext, $dispatch->finder_report_id );
			if ( null === $recipient ) {
				$this->store->mark_failed( $message_id, $now );
				return false; }
			$raw     = $this->protector->generate_token();
			$purpose = MessageSenderRole::OWNER === $recipient_role ? 'owner_secure_reply' : 'finder_continue_conversation';
			$link    = $this->store->issue_link( $dispatch->conversation->conversation_id, $purpose, $recipient_role, $this->protector->token_digest( $raw ), $now->add( new DateInterval( 'PT24H' ) ), $now );
			$message = MessageSenderRole::SYSTEM === $source ? null : $this->protector->decrypt_message( $dispatch->message->data->body_ciphertext, $dispatch->conversation->conversation_id, $source );
			if ( ! $this->store->dispatch_is_active( $message_id, $link->token_id, $now ) ) {
				$this->store->revoke_token( $link->token_id, $now );
				$this->store->mark_failed( $message_id, $now );
				return false;
			}
			if ( ! $this->sender->send( $recipient, $recipient_role, $message, $this->links->build( $raw ) ) ) {
				$this->store->revoke_token( $link->token_id, $now );
				$this->store->mark_failed( $message_id, $now );
				return false;
			}
			return $this->store->mark_sent( $message_id, $now );
		} catch ( \Throwable ) {
			if ( null !== $link ) {
				$this->store->revoke_token( $link->token_id, $now ); }
			$this->store->mark_failed( $message_id, $now );
			return false;
		}
	}
}
