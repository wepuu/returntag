<?php
/**
 * Owner Test Email sender port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use ReturnTag\TagCore\Domain\Auth\EmailAddress;

interface OwnerTestEmailSender {
	/**
	 * Submit one test message to the configured WordPress mailer.
	 *
	 * @param EmailAddress $recipient Server-resolved recipient.
	 */
	public function send( EmailAddress $recipient ): bool;
}
