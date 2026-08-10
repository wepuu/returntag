<?php
/**
 * Action Scheduler Finder Report adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Application\FinderReport\FinderReportProcessingScheduler;
use RuntimeException;
use Throwable;

/** Schedules unique report-ID-only evidence actions. */
final class ActionSchedulerFinderReportProcessingScheduler implements FinderReportProcessingScheduler {
	public const HOOK = 'returntag_process_finder_report_evidence';

	public const GROUP = 'returntag-finder-evidence';

	/** Confirm that intake, retry, recovery, and cleanup scheduling APIs exist. */
	public function is_available(): bool {
		return function_exists( 'as_enqueue_async_action' )
			&& function_exists( 'as_schedule_single_action' )
			&& function_exists( 'as_has_scheduled_action' )
			&& function_exists( 'as_schedule_recurring_action' );
	}

	/**
	 * Schedule one report with internal identifiers only.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 * @param int $delay_seconds Non-negative delay.
	 * @throws RuntimeException When the queue is unavailable.
	 */
	public function schedule( int $finder_report_id, int $delay_seconds = 0 ): void {
		if ( $finder_report_id < 1 || $delay_seconds < 0 ) {
			throw new RuntimeException( 'Finder evidence action could not be scheduled.' );
		}

		$arguments = array( 'finder_report_id' => $finder_report_id );

		try {
			if ( $delay_seconds > 0 ) {
				if ( ! function_exists( 'as_schedule_single_action' ) ) {
					throw new RuntimeException( 'Finder evidence queue is unavailable.' );
				}

				$action_id = \as_schedule_single_action( time() + $delay_seconds, self::HOOK, $arguments, self::GROUP, true, 20 );
			} else {
				if ( ! function_exists( 'as_enqueue_async_action' ) ) {
					throw new RuntimeException( 'Finder evidence queue is unavailable.' );
				}

				$action_id = \as_enqueue_async_action( self::HOOK, $arguments, self::GROUP, true, 20 );
			}

			if ( is_int( $action_id ) && $action_id > 0 ) {
				return;
			}

			if (
				function_exists( 'as_has_scheduled_action' )
				&& false !== \as_has_scheduled_action( self::HOOK, $arguments, self::GROUP )
			) {
				return;
			}

			throw new RuntimeException( 'Finder evidence queue is unavailable.' );
		} catch ( Throwable $exception ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Wrapped, never rendered.
			throw new RuntimeException( 'Finder evidence action could not be scheduled.', 0, $exception );
		}
	}
}
