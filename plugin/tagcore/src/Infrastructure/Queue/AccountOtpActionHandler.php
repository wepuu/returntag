<?php
/**
 * Owner Account OTP Worker adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Application\Auth\DispatchAccountOtp;

/**
 * Executes one at-most-once Account challenge dispatch.
 */
final readonly class AccountOtpActionHandler {
	/**
	 * Create the Worker adapter.
	 *
	 * @param DispatchAccountOtp $dispatch Worker use case.
	 */
	public function __construct( private DispatchAccountOtp $dispatch ) {
	}

	/** Register the internal Account OTP Worker hook. */
	public function register(): void {
		add_action( ActionSchedulerAccountOtpScheduler::HOOK, array( $this, 'handle' ), 10, 1 );
	}

	/**
	 * Dispatch one scheduled challenge.
	 *
	 * @param int $challenge_id Positive internal challenge ID.
	 */
	public function handle( int $challenge_id ): void {
		$this->dispatch->execute( $challenge_id );
	}
}
