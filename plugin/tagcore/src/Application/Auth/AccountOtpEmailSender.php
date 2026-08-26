<?php
/**
 * Owner Account OTP email port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use ReturnTag\TagCore\Domain\Auth\EmailAddress;

interface AccountOtpEmailSender {
	/**
	 * Submit one private OTP email to the requested recipient.
	 *
	 * @param EmailAddress $recipient Requested recipient.
	 * @param string       $code Exact six-digit code.
	 * @param string       $idempotency_key Opaque stable business key.
	 */
	public function send( EmailAddress $recipient, string $code, string $idempotency_key ): bool;
}
