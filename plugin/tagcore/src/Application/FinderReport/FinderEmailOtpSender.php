<?php
/**
 * Finder email OTP sender port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use ReturnTag\TagCore\Domain\Auth\EmailAddress;

interface FinderEmailOtpSender {
	/**
	 * Submit one private verification email.
	 *
	 * @param EmailAddress $recipient Private recipient.
	 * @param string       $code Six-digit code.
	 */
	public function send( EmailAddress $recipient, string $code ): bool;
}
