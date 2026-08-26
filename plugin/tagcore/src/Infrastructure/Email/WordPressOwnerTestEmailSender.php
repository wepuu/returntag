<?php
/**
 * WordPress Owner Test Email adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Email;

use ReturnTag\TagCore\Application\Account\OwnerTestEmailSender;
use ReturnTag\TagCore\Application\Email\TransactionalEmail;
use ReturnTag\TagCore\Application\Email\TransactionalEmailGateway;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/** Uses wp_mail so the configured WP Mail SMTP transport remains replaceable. */
final class WordPressOwnerTestEmailSender implements OwnerTestEmailSender {
	/**
	 * Create the business-specific adapter.
	 *
	 * @param TransactionalEmailGateway $gateway Provider-neutral gateway.
	 */
	public function __construct( private readonly TransactionalEmailGateway $gateway ) {}
	/**
	 * Submit one Test Email through wp_mail.
	 *
	 * @param EmailAddress $recipient Server-resolved Owner email.
	 * @param string       $idempotency_key Opaque stable business key.
	 */
	public function send( EmailAddress $recipient, string $idempotency_key ): bool {
		return $this->gateway->send( new TransactionalEmail( 'owner_test', $idempotency_key, $recipient, __( 'Your ForgeTag notification test', 'tagcore' ), __( "Your ForgeTag email notifications are connected to this address.\n\nNo action is required. This test confirms provider acceptance only; delivery is tracked separately.", 'tagcore' ) ) )->accepted;
	}
}
