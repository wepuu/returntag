<?php
/**
 * Activation OTP email port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/**
 * Sends one transactional OTP without exposing provider semantics.
 */
interface ActivationOtpEmailSender {
	/**
	 * Return true only when the provider accepted the submission.
	 *
	 * @param EmailAddress $recipient Verified target address.
	 * @param string       $code Six-digit code held only in memory.
	 * @param string       $idempotency_key Opaque stable business key.
	 */
	public function send( EmailAddress $recipient, string $code, string $idempotency_key ): bool;
}
