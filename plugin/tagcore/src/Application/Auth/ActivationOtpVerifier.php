<?php
/**
 * Activation OTP verification boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use ReturnTag\TagCore\Domain\Auth\ActivationOtpCode;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Verifies one activation OTP without authenticating or activating a Tag.
 */
interface ActivationOtpVerifier {
	/**
	 * Verify one submitted activation OTP.
	 *
	 * @param TagId             $tag_id Eligible public Tag.
	 * @param EmailAddress      $email Canonical email identity.
	 * @param ActivationOtpCode $code Exact six-digit code.
	 * @param string            $ip_address Direct client IP.
	 */
	public function execute(
		TagId $tag_id,
		EmailAddress $email,
		ActivationOtpCode $code,
		string $ip_address
	): ActivationOtpVerificationResult;
}
