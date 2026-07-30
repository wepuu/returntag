<?php
/**
 * Activation OTP scheduler port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

/**
 * Schedules a Worker using only an internal challenge identifier.
 */
interface ActivationOtpScheduler {
	/**
	 * Schedule one internal challenge.
	 *
	 * @param int $challenge_id Positive challenge identifier.
	 */
	public function schedule( int $challenge_id ): void;
}
