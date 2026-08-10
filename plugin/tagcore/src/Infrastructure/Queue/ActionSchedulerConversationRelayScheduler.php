<?php
/**
 * Action Scheduler Conversation relay adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Application\Conversation\ConversationRelayScheduler;
use RuntimeException;

/** Schedules unique Message-ID-only work. */
final class ActionSchedulerConversationRelayScheduler implements ConversationRelayScheduler {
	public const HOOK  = 'returntag_dispatch_conversation_message';
	public const GROUP = 'returntag-conversation-relay';
	/**
	 * Schedule one Message identifier.
	 *
	 * @param int $message_id Message identifier.
	 * @throws RuntimeException When the queue is unavailable.
	 */
	public function schedule( int $message_id ): void {
		if ( $message_id < 1 || ! function_exists( 'as_enqueue_async_action' ) ) {
			throw new RuntimeException( 'Conversation relay queue is unavailable.' ); }
		$id = \as_enqueue_async_action( self::HOOK, array( 'message_id' => $message_id ), self::GROUP, true, 10 );
		if ( ! is_int( $id ) || $id < 1 ) {
			throw new RuntimeException( 'Conversation relay queue is unavailable.' ); }
	}
}
