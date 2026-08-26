<?php
/**
 * WordPress Finder email OTP sender.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Email;

use ReturnTag\TagCore\Application\FinderReport\FinderEmailOtpSender;
use ReturnTag\TagCore\Application\Email\TransactionalEmail;
use ReturnTag\TagCore\Application\Email\TransactionalEmailGateway;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/** Sends a private, reply-disabled verification message. */
final class WordPressFinderEmailOtpSender implements FinderEmailOtpSender {
	/**
	 * Create the business-specific adapter.
	 *
	 * @param TransactionalEmailGateway $gateway Provider-neutral gateway.
	 */
	public function __construct( private readonly TransactionalEmailGateway $gateway ) {}
	/**
	 * Submit one private verification email.
	 *
	 * @param EmailAddress $recipient Private recipient.
	 * @param string       $code Six-digit OTP.
	 * @param string       $idempotency_key Opaque stable business key.
	 */
	public function send( EmailAddress $recipient, string $code, string $idempotency_key ): bool {
		if ( 1 !== preg_match( '/^[0-9]{6}$/D', $code ) ) {
			return false;
		}
		$body = sprintf(
			/* translators: %s: six-digit verification code. */
			__( "Your ReturnTag private conversation code is %s.\n\nIt expires in 10 minutes. If you did not request this code, you can ignore this email.", 'tagcore' ),
			$code
		);
		return $this->gateway->send( new TransactionalEmail( 'finder_email_otp', $idempotency_key, $recipient, __( 'Verify your ForgeTag email', 'tagcore' ), $body ) )->accepted;
	}
}
