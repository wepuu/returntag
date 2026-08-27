<?php
/**
 * Authentication challenge retention composition root.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Application\Auth\CleanupAuthChallenges;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAuthChallengeRetentionStore;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\SystemClock;
use wpdb;

/** Registers purpose-independent, hourly, bounded challenge cleanup. */
final class AuthChallengeRetentionBootstrap {
	public const CLEANUP_HOOK  = 'returntag_cleanup_auth_challenges';
	public const CLEANUP_GROUP = 'returntag-auth-challenge-retention';
	public const INTERVAL      = 3600;

	/** Register the cleanup worker and recurring schedule for the current site. */
	public static function register(): void {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			return;
		}

		$cleanup = new CleanupAuthChallenges(
			new WpdbAuthChallengeRetentionStore(
				new WpdbGateway( $wpdb ),
				new TableNames( $wpdb->prefix ),
				new DatabaseDateTimeCodec()
			),
			new SystemClock()
		);

		add_action(
			self::CLEANUP_HOOK,
			static function () use ( $cleanup ): void {
				$cleanup->execute();
			}
		);
		add_action(
			'action_scheduler_init',
			static function (): void {
				if (
					function_exists( 'as_has_scheduled_action' )
					&& function_exists( 'as_schedule_recurring_action' )
					&& false === \as_has_scheduled_action( self::CLEANUP_HOOK, array(), self::CLEANUP_GROUP )
				) {
					\as_schedule_recurring_action(
						time() + self::INTERVAL,
						self::INTERVAL,
						self::CLEANUP_HOOK,
						array(),
						self::CLEANUP_GROUP,
						true,
						30
					);
				}
			}
		);
	}
}
