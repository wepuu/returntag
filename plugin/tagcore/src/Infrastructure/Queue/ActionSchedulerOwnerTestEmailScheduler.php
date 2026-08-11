<?php
/**
 * Action Scheduler Owner Test Email adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Application\Account\OwnerTestEmailScheduler;
use RuntimeException;

/** Queues internal identifiers only. */
final class ActionSchedulerOwnerTestEmailScheduler implements OwnerTestEmailScheduler {
	public const HOOK  = 'returntag_dispatch_owner_test_email';
	public const GROUP = 'returntag-owner-test-email';
	/**
	 * Enqueue one unique identifier-only action.
	 *
	 * @param int $event_id Audit Event identifier.
	 * @param int $owner_id Server-derived Owner identifier.
	 * @throws RuntimeException When Action Scheduler is unavailable.
	 */
	public function schedule( int $event_id, int $owner_id ): void {
		if ( $event_id < 1 || $owner_id < 1 || ! function_exists( 'as_enqueue_async_action' ) ) {
			throw new RuntimeException( 'Owner test email queue is unavailable.' ); }
		$id = \as_enqueue_async_action(
			self::HOOK,
			array(
				'event_id' => $event_id,
				'owner_id' => $owner_id,
			),
			self::GROUP,
			true,
			10
		);
		if ( ! is_int( $id ) || $id < 1 ) {
			throw new RuntimeException( 'Owner test email queue is unavailable.' ); }
	}
}
