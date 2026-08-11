<?php
/**
 * Action Scheduler Owner Account OTP adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Application\Auth\AccountOtpScheduler;
use RuntimeException;
use Throwable;

/**
 * Persists only a numeric challenge ID in the queue.
 */
final class ActionSchedulerAccountOtpScheduler implements AccountOtpScheduler {
	public const HOOK = 'returntag_dispatch_account_otp';

	public const GROUP = 'returntag-account-otp';

	/**
	 * Enqueue one unique challenge-ID-only action.
	 *
	 * @param int $challenge_id Positive challenge identifier.
	 * @throws RuntimeException When Action Scheduler is unavailable.
	 */
	public function schedule( int $challenge_id ): void {
		if ( $challenge_id < 1 || ! function_exists( 'as_enqueue_async_action' ) ) {
			throw new RuntimeException( 'Account OTP queue is unavailable.' );
		}

		try {
			$action_id = \as_enqueue_async_action(
				self::HOOK,
				array( 'challenge_id' => $challenge_id ),
				self::GROUP,
				true,
				10
			);
		} catch ( Throwable $exception ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed message is thrown and never rendered.
			throw new RuntimeException( 'Account OTP queue is unavailable.', 0, $exception );
		}

		if ( ! is_int( $action_id ) || $action_id < 1 ) {
			throw new RuntimeException( 'Account OTP queue is unavailable.' );
		}
	}
}
