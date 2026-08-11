<?php
/**
 * WordPress Owner Account OTP email adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Email;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Auth\AccountOtpEmailSender;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/**
 * Submits one private transactional Account code with no reply routing.
 */
final class WordPressAccountOtpEmailSender implements AccountOtpEmailSender {
	/**
	 * Submit one Account code through WordPress mail.
	 *
	 * @param EmailAddress $recipient Requested recipient.
	 * @param string       $code Exact six-digit code.
	 * @throws InvalidArgumentException When the code shape is invalid.
	 */
	public function send( EmailAddress $recipient, string $code ): bool {
		if ( 1 !== preg_match( '/^\d{6}$/D', $code ) ) {
			throw new InvalidArgumentException( 'Account OTP code is invalid.' );
		}

		$subject = __( 'Your ForgeTag account verification code', 'tagcore' );
		$body    = sprintf(
			/* translators: %s: six-digit verification code. */
			__( "Your ForgeTag account verification code is %s.\n\nIt expires in 10 minutes. If you did not request this code, you can ignore this email.", 'tagcore' ),
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
