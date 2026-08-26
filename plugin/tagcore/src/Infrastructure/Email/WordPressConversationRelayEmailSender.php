<?php
/**
 * WordPress privacy-safe relay email sender.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Infrastructure\Email;

use ReturnTag\TagCore\Application\Conversation\ConversationRelayEmailSender;
use ReturnTag\TagCore\Application\Email\TransactionalEmail;
use ReturnTag\TagCore\Application\Email\TransactionalEmailGateway;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;
/** Sends plaintext relay mail without cross-party headers. */
final class WordPressConversationRelayEmailSender implements ConversationRelayEmailSender {
	/**
	 * Create the business-specific adapter.
	 *
	 * @param TransactionalEmailGateway $gateway Provider-neutral gateway.
	 */
	public function __construct( private readonly TransactionalEmailGateway $gateway ) {}
	/**
	 * Send one privacy-safe email.
	 *
	 * @param EmailAddress      $recipient Recipient.
	 * @param MessageSenderRole $recipient_role Recipient role.
	 * @param string|null       $message Optional body.
	 * @param string            $continue_url Secure URL.
	 * @param string            $idempotency_key Opaque stable business key.
	 */
	public function send( EmailAddress $recipient, MessageSenderRole $recipient_role, ?string $message, string $continue_url, string $idempotency_key ): bool {
		$intro = MessageSenderRole::OWNER === $recipient_role ? __( 'A verified finder is ready for private contact.', 'tagcore' ) : __( 'The owner sent you a private message.', 'tagcore' );
		$body  = $intro . "\n\n";
		if ( null !== $message ) {
			$body .= $message . "\n\n";
		}
		/* translators: %s: Same-site Secure Reply URL. */
		$body .= sprintf( __( 'Continue securely: %s', 'tagcore' ), $continue_url ) . "\n\n" . __( 'This link expires in 24 hours. Replies are private and email addresses are never shared.', 'tagcore' );

		return $this->gateway->send( new TransactionalEmail( 'conversation_relay', $idempotency_key, $recipient, __( 'A private ForgeTag message is ready', 'tagcore' ), $body ) )->accepted;
	}
}
