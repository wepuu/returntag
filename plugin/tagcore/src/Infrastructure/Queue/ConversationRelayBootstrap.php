<?php
/**
 * Conversation relay Worker composition root.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Infrastructure\Conversation\ConversationRelayRuntimeFactory;
use wpdb;

/** Registers Message-ID-only delivery and hourly bounded recovery. */
final class ConversationRelayBootstrap {
	public const RECOVERY_HOOK = 'returntag_recover_conversation_messages';
	/** Register Worker and recovery hooks. */
	public static function register(): void {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return; }
		$runtime = ConversationRelayRuntimeFactory::create( $wpdb );
		if ( null === $runtime ) {
			return; }
		add_action(
			ActionSchedulerConversationRelayScheduler::HOOK,
			static function ( int $message_id ) use ( $runtime ): void {
				$runtime->dispatch->execute( $message_id );
			},
			10,
			1
		);
		add_action(
			self::RECOVERY_HOOK,
			static function () use ( $runtime ): void {
				$runtime->converge->execute( 50 );
			}
		);
		add_action(
			'action_scheduler_init',
			static function (): void {
				if ( function_exists( 'as_has_scheduled_action' ) && function_exists( 'as_schedule_recurring_action' ) && false === \as_has_scheduled_action( self::RECOVERY_HOOK, array(), ActionSchedulerConversationRelayScheduler::GROUP ) ) {
					\as_schedule_recurring_action( time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS, self::RECOVERY_HOOK, array(), ActionSchedulerConversationRelayScheduler::GROUP, true, 20 );
				}
			}
		);
	}
}
