<?php
/**
 * Activation OTP Worker adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Application\Auth\DispatchActivationOtp;

/**
 * Executes one at-most-once challenge dispatch without automatic retries.
 */
final readonly class ActivationOtpActionHandler {
	/**
	 * Create the queue handler.
	 *
	 * @param DispatchActivationOtp $dispatch Worker use case.
	 */
	public function __construct( private DispatchActivationOtp $dispatch ) {
	}

	/**
	 * Register the internal Worker hook.
	 */
	public function register(): void {
		add_action(
			ActionSchedulerActivationOtpScheduler::HOOK,
			array( $this, 'handle' ),
			10,
			1
		);
	}

	/**
	 * Dispatch one scheduled challenge.
	 *
	 * @param int $challenge_id Internal challenge identifier.
	 */
	public function handle( int $challenge_id ): void {
		$this->dispatch->execute( $challenge_id );
	}
}
