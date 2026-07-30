<?php
/**
 * Action Scheduler activation OTP adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Application\Auth\ActivationOtpScheduler;
use RuntimeException;
use Throwable;

/**
 * Persists only one numeric challenge ID in the queue.
 */
final class ActionSchedulerActivationOtpScheduler implements ActivationOtpScheduler {
	public const HOOK = 'returntag_dispatch_activation_otp';

	public const GROUP = 'returntag-activation-otp';

	public const PRIORITY = 10;

	/**
	 * Enqueue one unique challenge-ID-only action.
	 *
	 * @param int $challenge_id Positive internal challenge ID.
	 * @throws RuntimeException When Action Scheduler is unavailable.
	 */
	public function schedule( int $challenge_id ): void {
		if ( $challenge_id < 1 || ! function_exists( 'as_enqueue_async_action' ) ) {
			throw new RuntimeException( 'Activation OTP queue is unavailable.' );
		}

		try {
			$action_id = \as_enqueue_async_action(
				self::HOOK,
				array( 'challenge_id' => $challenge_id ),
				self::GROUP,
				true,
				self::PRIORITY
			);
		} catch ( Throwable $exception ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed message is thrown and the cause is never rendered.
			throw new RuntimeException( 'Activation OTP queue is unavailable.', 0, $exception );
		}

		if ( ! is_int( $action_id ) || $action_id < 1 ) {
			throw new RuntimeException( 'Activation OTP queue is unavailable.' );
		}
	}
}
