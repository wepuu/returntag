<?php
/**
 * Authorized relay thread reader.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Application\Conversation;

use ReturnTag\TagCore\Application\Clock;

/** Resolves a session and decrypts only its bounded Conversation. */
final readonly class ReadConversationThread {
	/**
	 * Create the thread reader.
	 *
	 * @param ConversationRelayStore     $store Store.
	 * @param ConversationRelayProtector $protector Protector.
	 * @param Clock                      $clock UTC clock.
	 */
	public function __construct( private ConversationRelayStore $store, private ConversationRelayProtector $protector, private Clock $clock ) {}

	/**
	 * Read one authorized thread.
	 *
	 * @param string $raw_session Raw session Token.
	 * @return array{identity: ConversationRelayIdentity, messages: list<ConversationRelayMessage>}|null
	 */
	public function execute( string $raw_session ): ?array {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{43}$/D', $raw_session ) ) {
			return null; }
		$identity = $this->store->resolve_session( $this->protector->token_digest( $raw_session ), $this->clock->now() );
		if ( null === $identity ) {
			return null; }
		$messages = array();
		foreach ( $this->store->list_human_messages( $identity, $this->clock->now() ) as $record ) {
			$messages[] = new ConversationRelayMessage(
				$record->message_id,
				$record->data->sender_role,
				$this->protector->decrypt_message( $record->data->body_ciphertext, $identity->conversation_id, $record->data->sender_role ),
				$record->data->created_at
			);
		}
		return array(
			'identity' => $identity,
			'messages' => $messages,
		);
	}
}
