<?php
/**
 * Owner Transfer Action Scheduler handler.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Application\Account\DispatchOwnerTransferInvitation;
/** Adapts the internal Hook to the invitation Worker. */
final readonly class OwnerTransferActionHandler {
	/**
	 * Create the Hook handler.
	 *
	 * @param DispatchOwnerTransferInvitation $dispatch Worker use case.
	 */
	public function __construct( private DispatchOwnerTransferInvitation $dispatch ) {}

	/** Register the internal Worker Hook. */
	public function register(): void {
		add_action( ActionSchedulerOwnerTransferScheduler::HOOK, array( $this, 'handle' ) );
	}

	/**
	 * Handle one internal Transfer identifier.
	 *
	 * @param int $transfer_id Internal Transfer identifier.
	 */
	public function handle( int $transfer_id ): void {
		$this->dispatch->execute( $transfer_id ); }
}
