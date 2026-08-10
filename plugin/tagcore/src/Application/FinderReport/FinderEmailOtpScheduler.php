<?php
/**
 * Finder email OTP scheduling port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

interface FinderEmailOtpScheduler {
	/**
	 * Schedule one challenge-ID-only dispatch.
	 *
	 * @param int $challenge_id Internal challenge identifier.
	 */
	public function schedule( int $challenge_id ): void;
}
