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
	 * @param string       $idempotency_key Opaque stable business key.
	 */
	public function send( EmailAddress $recipient, string $idempotency_key ): bool;
}
