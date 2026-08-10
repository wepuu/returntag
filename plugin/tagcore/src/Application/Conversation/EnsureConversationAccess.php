<?php
/**
 * Ensure initial secure-reply availability.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Application\Conversation;

use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;

/** Creates exactly one queue-only system Message after both prerequisites exist. */
final readonly class EnsureConversationAccess {
	/**
	 * Create the availability service.
	 *
	 * @param ConversationRelayStore     $store Store.
	 * @param ConversationRelayProtector $protector Protector.
	 * @param ConversationRelayScheduler $scheduler Scheduler.
	 * @param Clock                      $clock UTC clock.
	 */
	public function __construct(
		private ConversationRelayStore $store,
		private ConversationRelayProtector $protector,
		private ConversationRelayScheduler $scheduler,
		private Clock $clock
	) {}

	/**
	 * Create and schedule the unique system access Message.
	 *
	 * @param int $finder_report_id Report identifier.
	 */
	public function execute( int $finder_report_id ): void {
		if ( $finder_report_id < 1 ) {
			return; }
		$conversation_id = $this->store->conversation_id_for_notified_report( $finder_report_id, $this->clock->now() );
		if ( null === $conversation_id ) {
			return; }
		$message = $this->store->ensure_access_message(
			$finder_report_id,
			$this->protector->encrypt_message( 'Private reply access is ready.', $conversation_id, MessageSenderRole::SYSTEM ),
			$this->clock->now()
		);
		if ( null !== $message ) {
			try {
				$this->scheduler->schedule( $message->message_id );
			} catch ( \Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Bounded recovery schedules unclaimed work.
				// Recovery schedules it.
			}
		}
	}
}
