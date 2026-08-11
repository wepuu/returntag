<?php
/**
 * Owner Test Email Action Scheduler handler.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Application\Account\DispatchOwnerTestEmail;

/** Adapts the internal Hook to the Application Worker. */
final readonly class OwnerTestEmailActionHandler {
	/**
	 * Create the Hook handler.
	 *
	 * @param DispatchOwnerTestEmail $dispatch Worker use case.
	 */
	public function __construct( private DispatchOwnerTestEmail $dispatch ) {}

	/** Register the internal Worker Hook. */
	public function register(): void {
		add_action( ActionSchedulerOwnerTestEmailScheduler::HOOK, array( $this, 'handle' ), 10, 2 ); }
	/**
	 * Handle one identifier-only queued action.
	 *
	 * @param int $event_id Request Event identifier.
	 * @param int $owner_id Server-derived Owner identifier.
	 */
	public function handle( int $event_id, int $owner_id ): void {
		$this->dispatch->execute( $event_id, $owner_id ); }
}
