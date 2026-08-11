<?php
/**
 * Owner Transfer email sender port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use ReturnTag\TagCore\Domain\Auth\EmailAddress;

interface OwnerTransferEmailSender {
	/**
	 * Submit one private invitation without reply routing.
	 *
	 * @param EmailAddress $recipient Encrypted-at-rest recipient resolved in Worker memory.
	 * @param string       $url One-time invitation URL.
	 */
	public function send( EmailAddress $recipient, string $url ): bool;
}
