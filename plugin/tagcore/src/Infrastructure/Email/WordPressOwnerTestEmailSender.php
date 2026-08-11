<?php
/**
 * WordPress Owner Test Email adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Email;

use ReturnTag\TagCore\Application\Account\OwnerTestEmailSender;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/** Uses wp_mail so the configured WP Mail SMTP transport remains replaceable. */
final class WordPressOwnerTestEmailSender implements OwnerTestEmailSender {
	/**
	 * Submit one Test Email through wp_mail.
	 *
	 * @param EmailAddress $recipient Server-resolved Owner email.
	 */
	public function send( EmailAddress $recipient ): bool {
		return wp_mail(
			$recipient->value,
			__( 'Your ForgeTag notification test', 'tagcore' ),
			__( "Your ForgeTag email notifications are connected to this address.\n\nNo action is required. This test confirms only that WordPress accepted the message for sending; it does not confirm provider delivery.", 'tagcore' ),
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}
}
