<?php
/**
 * WordPress activation OTP email adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Email;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Auth\ActivationOtpEmailSender;
use ReturnTag\TagCore\Application\Email\TransactionalEmail;
use ReturnTag\TagCore\Application\Email\TransactionalEmailGateway;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/**
 * Submits one private transactional message with no reply routing.
 */
final class WordPressActivationOtpEmailSender implements ActivationOtpEmailSender {
	/**
	 * Create the business-specific adapter.
	 *
	 * @param TransactionalEmailGateway $gateway Provider-neutral gateway.
	 */
	public function __construct( private readonly TransactionalEmailGateway $gateway ) {}
	/**
	 * Submit one OTP email through WordPress.
	 *
	 * @param EmailAddress $recipient Target address.
	 * @param string       $code Six-digit code.
	 * @param string       $idempotency_key Opaque stable business key.
	 * @throws InvalidArgumentException When the code shape is invalid.
	 */
	public function send( EmailAddress $recipient, string $code, string $idempotency_key ): bool {
		if ( 1 !== preg_match( '/^\d{6}$/D', $code ) ) {
			throw new InvalidArgumentException( 'OTP code is invalid.' );
		}

		$subject = __( 'Your ReturnTag verification code', 'tagcore' );
		$body    = sprintf(
			/* translators: %s: six-digit verification code. */
			__( "Your ReturnTag verification code is %s.\n\nIt expires in 10 minutes. If you did not request this code, you can ignore this email.", 'tagcore' ),
			$code
		);

		return $this->gateway->send( new TransactionalEmail( 'activation_otp', $idempotency_key, $recipient, $subject, $body ) )->accepted;
	}
}
