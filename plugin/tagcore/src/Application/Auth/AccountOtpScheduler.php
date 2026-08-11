<?php
/**
 * Owner Account OTP scheduler port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

interface AccountOtpScheduler {
	/**
	 * Schedule one challenge-ID-only Account OTP action.
	 *
	 * @param int $challenge_id Positive challenge identifier.
	 */
	public function schedule( int $challenge_id ): void;
}
