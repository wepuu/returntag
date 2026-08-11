<?php
/**
 * Action Scheduler Owner Transfer adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Application\Account\OwnerTransferScheduler;
use RuntimeException;
/** Queues internal Transfer identifiers only. */
final class ActionSchedulerOwnerTransferScheduler implements OwnerTransferScheduler {
	public const HOOK  = 'returntag_dispatch_owner_transfer';
	public const GROUP = 'returntag-owner-transfer';
	/**
	 * Enqueue one unique Transfer identifier.
	 *
	 * @param int $transfer_id Internal Transfer identifier.
	 * @throws RuntimeException When Action Scheduler is unavailable.
	 */
	public function schedule( int $transfer_id ): void {
		if ( $transfer_id < 1 || ! function_exists( 'as_enqueue_async_action' ) ) {
			throw new RuntimeException( 'Transfer queue unavailable.' );
		} $id = \as_enqueue_async_action( self::HOOK, array( 'transfer_id' => $transfer_id ), self::GROUP, true, 10 );
		if ( ! is_int( $id ) || $id < 1 ) {
			throw new RuntimeException( 'Transfer queue unavailable.' ); } }
}
