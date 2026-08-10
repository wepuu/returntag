<?php
/**
 * WordPress Finder email OTP sender.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Email;

use ReturnTag\TagCore\Application\FinderReport\FinderEmailOtpSender;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/** Sends a private, reply-disabled verification message. */
final class WordPressFinderEmailOtpSender implements FinderEmailOtpSender {
	/**
	 * Submit one private verification email.
	 *
	 * @param EmailAddress $recipient Private recipient.
	 * @param string       $code Six-digit OTP.
	 */
	public function send( EmailAddress $recipient, string $code ): bool {
		if ( 1 !== preg_match( '/^[0-9]{6}$/D', $code ) ) {
			return false;
		}
		$body = sprintf(
			/* translators: %s: six-digit verification code. */
			__( "Your ReturnTag private conversation code is %s.\n\nIt expires in 10 minutes. If you did not request this code, you can ignore this email.", 'tagcore' ),
			$code
		);
		return wp_mail( $recipient->value, __( 'Verify your ReturnTag email', 'tagcore' ), $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}
}
