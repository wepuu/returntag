<?php
/**
 * Finder Report background handlers.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use DateInterval;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FinderReport\CleanupFinderReportEvidence;
use ReturnTag\TagCore\Application\FinderReport\ProcessFinderReportEvidence;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportMediaRepository;
use RuntimeException;
use Throwable;

/** Runs processing, recovery, and bounded retention cleanup. */
final readonly class FinderReportActionHandler {
	public const RECOVERY_HOOK = 'returntag_recover_finder_report_evidence';

	public const CLEANUP_HOOK = 'returntag_cleanup_finder_report_evidence';

	/**
	 * Create the handler.
	 *
	 * @param ProcessFinderReportEvidence                           $process Processing use case.
	 * @param CleanupFinderReportEvidence                           $cleanup Cleanup use case.
	 * @param FinderReportMediaRepository                           $media Media persistence.
	 * @param ActionSchedulerFinderReportProcessingScheduler        $scheduler Queue scheduler.
	 * @param ActionSchedulerFinderReportOwnerNotificationScheduler $notifications Owner notification queue.
	 * @param Clock                                                 $clock UTC clock.
	 */
	public function __construct(
		private ProcessFinderReportEvidence $process,
		private CleanupFinderReportEvidence $cleanup,
		private FinderReportMediaRepository $media,
		private ActionSchedulerFinderReportProcessingScheduler $scheduler,
		private ActionSchedulerFinderReportOwnerNotificationScheduler $notifications,
		private Clock $clock
	) {
	}

	/** Register queue hooks and maintenance scheduling. */
	public function register(): void {
		add_action( ActionSchedulerFinderReportProcessingScheduler::HOOK, array( $this, 'process' ), 10, 1 );
		add_action( self::RECOVERY_HOOK, array( $this, 'recover' ) );
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup' ) );
		add_action( 'action_scheduler_init', array( $this, 'schedule_maintenance' ) );
	}

	/**
	 * Process one report using a fixed privacy-safe failure.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 * @throws RuntimeException When processing fails.
	 */
	public function process( int $finder_report_id ): void {
		try {
			$this->process->execute( $finder_report_id );
		} catch ( Throwable $exception ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Wrapped, never rendered.
			throw new RuntimeException( 'Finder evidence action failed.', 0, $exception );
		}

		try {
			$this->notifications->schedule( $finder_report_id );
		} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Notification recovery scans ready reports.
			// Hourly notification recovery owns missing queue work.
		}
	}

	/** Re-enqueue a bounded batch of missing or stale work. */
	public function recover(): void {
		$now = $this->clock->now();

		foreach ( $this->media->find_processable( $now, $now->sub( new DateInterval( 'PT15M' ) ), 50 ) as $report_id ) {
			$this->scheduler->schedule( $report_id );
		}
	}

	/** Delete one bounded expiry batch. */
	public function cleanup(): void {
		$this->cleanup->execute( 50 );
	}

	/** Schedule hourly recovery and cleanup exactly once. */
	public function schedule_maintenance(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		foreach ( array( self::RECOVERY_HOOK, self::CLEANUP_HOOK ) as $hook ) {
			if ( false === \as_has_scheduled_action( $hook, array(), ActionSchedulerFinderReportProcessingScheduler::GROUP ) ) {
				\as_schedule_recurring_action(
					time() + HOUR_IN_SECONDS,
					HOUR_IN_SECONDS,
					$hook,
					array(),
					ActionSchedulerFinderReportProcessingScheduler::GROUP,
					true,
					20
				);
			}
		}
	}
}
