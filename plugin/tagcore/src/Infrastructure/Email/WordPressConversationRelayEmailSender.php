<?php
/**
 * WordPress privacy-safe relay email sender.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Infrastructure\Email;

use ReturnTag\TagCore\Application\Conversation\ConversationRelayEmailSender;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;
use Throwable;
/** Sends plaintext relay mail without cross-party headers. */
final class WordPressConversationRelayEmailSender implements ConversationRelayEmailSender {
	/**
	 * Send one privacy-safe email.
	 *
	 * @param EmailAddress      $recipient Recipient.
	 * @param MessageSenderRole $recipient_role Recipient role.
	 * @param string|null       $message Optional body.
	 * @param string            $continue_url Secure URL.
	 */
	public function send( EmailAddress $recipient, MessageSenderRole $recipient_role, ?string $message, string $continue_url ): bool {
		$intro = MessageSenderRole::OWNER === $recipient_role ? __( 'A verified finder is ready for private contact.', 'tagcore' ) : __( 'The owner sent you a private message.', 'tagcore' );
		$body  = $intro . "\n\n";
		if ( null !== $message ) {
			$body .= $message . "\n\n";
		}
		/* translators: %s: Same-site Secure Reply URL. */
		$body .= sprintf( __( 'Continue securely: %s', 'tagcore' ), $continue_url ) . "\n\n" . __( 'This link expires in 24 hours. Replies are private and email addresses are never shared.', 'tagcore' );

		$header_sanitizer = static function ( mixed $mailer ): void {
			if ( ! $mailer instanceof \PHPMailer\PHPMailer\PHPMailer ) {
				return;
			}
			$mailer->clearReplyTos();
			$mailer->clearCCs();
			$mailer->clearBCCs();
		};
		add_action( 'phpmailer_init', $header_sanitizer, PHP_INT_MAX );
		try {
			return wp_mail( $recipient->value, __( 'A private ForgeTag message is ready', 'tagcore' ), $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
		} catch ( Throwable ) {
			return false;
		} finally {
			remove_action( 'phpmailer_init', $header_sanitizer, PHP_INT_MAX );
		}
	}
}
