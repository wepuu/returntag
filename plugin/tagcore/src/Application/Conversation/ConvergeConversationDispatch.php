<?php
/**
 * Recover bounded relay work.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Application\Conversation;

use DateInterval;
use ReturnTag\TagCore\Application\Clock;

/** Fails ambiguous claims and reschedules only never-claimed Messages. */
final readonly class ConvergeConversationDispatch {
	/**
	 * Create the recovery service.
	 *
	 * @param ConversationRelayStore     $store Store.
	 * @param ConversationRelayScheduler $scheduler Scheduler.
	 * @param Clock                      $clock UTC clock.
	 */
	public function __construct( private ConversationRelayStore $store, private ConversationRelayScheduler $scheduler, private Clock $clock ) {}
	/**
	 * Recover a bounded set of pending and stale work.
	 *
	 * @param int $limit Bound.
	 */
	public function execute( int $limit = 50 ): void {
		$now = $this->clock->now();
		$this->store->fail_stale_claims( $now->sub( new DateInterval( 'PT15M' ) ), $now, $limit );
		foreach ( $this->store->pending_message_ids( $limit ) as $id ) {
			try {
				$this->scheduler->schedule( $id );
			} catch ( \Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- The next bounded recovery retries scheduling.
				// Next recovery retries.
			}
		}
	}
}
