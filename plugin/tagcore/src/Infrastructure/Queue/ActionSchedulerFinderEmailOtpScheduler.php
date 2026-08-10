<?php
/**
 * Action Scheduler Finder email OTP adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Application\FinderReport\FinderEmailOtpScheduler;
use RuntimeException;

/** Enqueues only the internal challenge identifier. */
final class ActionSchedulerFinderEmailOtpScheduler implements FinderEmailOtpScheduler {
	public const HOOK  = 'returntag_dispatch_finder_email_otp';
	public const GROUP = 'returntag-finder-email-otp';

	/**
	 * Enqueue one challenge-ID-only action.
	 *
	 * @param int $challenge_id Internal challenge identifier.
	 * @throws RuntimeException When Action Scheduler is unavailable.
	 */
	public function schedule( int $challenge_id ): void {
		if ( $challenge_id < 1 || ! function_exists( 'as_enqueue_async_action' ) ) {
			throw new RuntimeException( 'Finder email OTP queue is unavailable.' );
		}
		$id = \as_enqueue_async_action( self::HOOK, array( 'challenge_id' => $challenge_id ), self::GROUP, true, 10 );
		if ( ! is_int( $id ) || $id < 1 ) {
			throw new RuntimeException( 'Finder email OTP queue is unavailable.' );
		}
	}
}
