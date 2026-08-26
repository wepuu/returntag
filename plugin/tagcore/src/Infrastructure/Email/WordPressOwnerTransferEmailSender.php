<?php
/**
 * WordPress Owner Transfer email adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Email;

use ReturnTag\TagCore\Application\Account\OwnerTransferEmailSender;
use ReturnTag\TagCore\Application\Email\TransactionalEmail;
use ReturnTag\TagCore\Application\Email\TransactionalEmailGateway;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/** Sends a private plaintext invitation through wp_mail. */
final class WordPressOwnerTransferEmailSender implements OwnerTransferEmailSender {
	/**
	 * Create the business-specific adapter.
	 *
	 * @param TransactionalEmailGateway $gateway Provider-neutral gateway.
	 */
	public function __construct( private readonly TransactionalEmailGateway $gateway ) {}
	/**
	 * Submit one private Transfer invitation through wp_mail.
	 *
	 * @param EmailAddress $recipient Decrypted target email.
	 * @param string       $url One-time invitation URL.
	 * @param string       $idempotency_key Opaque stable business key.
	 */
	public function send( EmailAddress $recipient, string $url, string $idempotency_key ): bool {
		$body = sprintf( /* translators: %s: secure transfer acceptance URL. */ __( "A ForgeTag owner invited you to take ownership. Sign in with this email and explicitly accept the transfer:\n\n%s\n\nThe link expires in 24 hours. Ignore it if you were not expecting this invitation.", 'tagcore' ), $url );
		return $this->gateway->send( new TransactionalEmail( 'owner_transfer', $idempotency_key, $recipient, __( 'A ForgeTag transfer is waiting for you', 'tagcore' ), $body ) )->accepted;
	}
}
