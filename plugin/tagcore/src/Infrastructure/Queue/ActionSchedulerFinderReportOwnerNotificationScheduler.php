<?php
/**
 * Action Scheduler Finder Report Owner notification adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Application\FinderReport\FinderReportOwnerNotificationScheduler;
use RuntimeException;
use Throwable;

/** Schedules unique report-ID-only Owner notification actions. */
final class ActionSchedulerFinderReportOwnerNotificationScheduler implements FinderReportOwnerNotificationScheduler {
	public const HOOK = 'returntag_notify_finder_report_owner';

	public const GROUP = 'returntag-finder-notification';

	public const PRIORITY = 10;

	/**
	 * Queue one unique internal report identifier.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 * @throws RuntimeException When the durable queue is unavailable.
	 */
	public function schedule( int $finder_report_id ): void {
		if ( $finder_report_id < 1 || ! function_exists( 'as_enqueue_async_action' ) ) {
			throw new RuntimeException( 'Finder Report notification queue is unavailable.' );
		}

		try {
			$action_id = \as_enqueue_async_action(
				self::HOOK,
				array( 'finder_report_id' => $finder_report_id ),
				self::GROUP,
				true,
				self::PRIORITY
			);
		} catch ( Throwable $exception ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed message is thrown and the cause is never rendered.
			throw new RuntimeException( 'Finder Report notification queue is unavailable.', 0, $exception );
		}

		if ( ! is_int( $action_id ) || $action_id < 1 ) {
			throw new RuntimeException( 'Finder Report notification queue is unavailable.' );
		}
	}

	/** Check the durable Action Scheduler API at execution time. */
	public function is_available(): bool {
		return function_exists( 'as_enqueue_async_action' )
			&& function_exists( 'as_has_scheduled_action' )
			&& function_exists( 'as_schedule_recurring_action' );
	}
}
