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
use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/**
 * Submits one private transactional message with no reply routing.
 */
final class WordPressActivationOtpEmailSender implements ActivationOtpEmailSender {
	/**
	 * Submit one OTP email through WordPress.
	 *
	 * @param EmailAddress $recipient Target address.
	 * @param string       $code Six-digit code.
	 * @throws InvalidArgumentException When the code shape is invalid.
	 */
	public function send( EmailAddress $recipient, string $code ): bool {
		if ( 1 !== preg_match( '/^\d{6}$/D', $code ) ) {
			throw new InvalidArgumentException( 'OTP code is invalid.' );
		}

		$subject = __( 'Your ReturnTag verification code', 'tagcore' );
		$body    = sprintf(
			/* translators: %s: six-digit verification code. */
			__( "Your ReturnTag verification code is %s.\n\nIt expires in 10 minutes. If you did not request this code, you can ignore this email.", 'tagcore' ),
			$code
		);

		return wp_mail(
			$recipient->value,
			$subject,
			$body,
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}
}
