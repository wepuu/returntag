<?php
/**
 * Finder Report worker composition root.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Infrastructure\FinderReport\FinderReportRuntimeFactory;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionFinderEmailRateLimiter;
use wpdb;

/** Registers workers only when private runtime configuration is safe. */
final class FinderReportBootstrap {
	public const EMAIL_RATE_CLEANUP_HOOK  = 'returntag_cleanup_finder_email_rate_limits';
	public const EMAIL_RATE_CLEANUP_GROUP = 'returntag-finder-email-maintenance';

	/** Register Stage 3 processing and Stage 4 notification hooks. */
	public static function register(): void {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			return;
		}

		$email_limiter = new WordPressOptionFinderEmailRateLimiter( $wpdb, get_current_blog_id() );
		add_action(
			self::EMAIL_RATE_CLEANUP_HOOK,
			static function () use ( $email_limiter ): void {
				$email_limiter->cleanup_expired();
			}
		);
		add_action(
			'action_scheduler_init',
			static function (): void {
				if (
					function_exists( 'as_has_scheduled_action' )
					&& function_exists( 'as_schedule_recurring_action' )
					&& false === \as_has_scheduled_action( self::EMAIL_RATE_CLEANUP_HOOK, array(), self::EMAIL_RATE_CLEANUP_GROUP )
				) {
					\as_schedule_recurring_action(
						time() + DAY_IN_SECONDS,
						DAY_IN_SECONDS,
						self::EMAIL_RATE_CLEANUP_HOOK,
						array(),
						self::EMAIL_RATE_CLEANUP_GROUP,
						true,
						30
					);
				}
			}
		);

		$runtime = FinderReportRuntimeFactory::create( $wpdb );

		if ( null === $runtime ) {
			return;
		}

		( new FinderReportActionHandler(
			$runtime->process,
			$runtime->cleanup,
			$runtime->media,
			$runtime->scheduler,
			$runtime->notification_scheduler,
			new \ReturnTag\TagCore\Infrastructure\SystemClock()
		) )->register();

		( new FinderReportNotificationActionHandler(
			$runtime->notify,
			$runtime->converge,
			$runtime->reports,
			$runtime->notification_scheduler,
			new \ReturnTag\TagCore\Infrastructure\SystemClock(),
			$runtime->ensure_conversation_access
		) )->register();

		if ( null !== $runtime->email_dispatch ) {
			add_action(
				ActionSchedulerFinderEmailOtpScheduler::HOOK,
				static function ( int $challenge_id ) use ( $runtime ): void {
					$runtime->email_dispatch->execute( $challenge_id );
				},
				10,
				1
			);
		}
	}
}
