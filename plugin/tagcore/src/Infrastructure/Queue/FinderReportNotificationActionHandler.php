<?php
/**
 * Finder Report Owner notification background handlers.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FinderReport\ConvergeStaleFinderReportNotifications;
use ReturnTag\TagCore\Application\FinderReport\NotifyFinderReportOwner;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportRepository;
use Throwable;
use ReturnTag\TagCore\Application\Conversation\EnsureConversationAccess;

/** Runs delivery, missing-work recovery, and stale-claim convergence. */
final readonly class FinderReportNotificationActionHandler {
	public const RECOVERY_HOOK = 'returntag_recover_finder_report_notifications';

	/**
	 * Create the handler.
	 *
	 * @param NotifyFinderReportOwner                               $notify Notification use case.
	 * @param ConvergeStaleFinderReportNotifications                $converge Stale-claim convergence.
	 * @param FinderReportRepository                                $reports Report persistence.
	 * @param ActionSchedulerFinderReportOwnerNotificationScheduler $scheduler Notification scheduler.
	 * @param Clock                                                 $clock UTC clock.
	 * @param EnsureConversationAccess|null                         $ensure_access Optional Stage 6 access trigger.
	 */
	public function __construct(
		private NotifyFinderReportOwner $notify,
		private ConvergeStaleFinderReportNotifications $converge,
		private FinderReportRepository $reports,
		private ActionSchedulerFinderReportOwnerNotificationScheduler $scheduler,
		private Clock $clock,
		private ?EnsureConversationAccess $ensure_access = null
	) {
	}

	/** Register queue hooks and hourly recovery. */
	public function register(): void {
		add_action( ActionSchedulerFinderReportOwnerNotificationScheduler::HOOK, array( $this, 'notify' ), 10, 1 );
		add_action( self::RECOVERY_HOOK, array( $this, 'recover' ) );
		add_action( 'action_scheduler_init', array( $this, 'schedule_recovery' ) );
	}

	/**
	 * Execute one terminally convergent notification attempt.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 */
	public function notify( int $finder_report_id ): void {
		$result = $this->notify->execute( $finder_report_id );
		if ( \ReturnTag\TagCore\Application\FinderReport\FinderReportOwnerNotificationResult::SENT === $result ) {
			$this->ensure_access?->execute( $finder_report_id );
		}
	}

	/** Schedule missing ready work and fail stale ambiguous claims closed. */
	public function recover(): void {
		$this->converge->execute( 50 );
		$now = $this->clock->now();

		foreach ( $this->reports->find_notifiable( $now, 50 ) as $finder_report_id ) {
			try {
				$this->scheduler->schedule( $finder_report_id );
			} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- The next bounded recovery run retries scheduling.
				// Leave the unclaimed report eligible for the next recovery run.
			}
		}
	}

	/** Schedule hourly recovery exactly once. */
	public function schedule_recovery(): void {
		if (
			! $this->scheduler->is_available()
			|| ! function_exists( 'as_has_scheduled_action' )
			|| ! function_exists( 'as_schedule_recurring_action' )
		) {
			return;
		}

		if ( false === \as_has_scheduled_action( self::RECOVERY_HOOK, array(), ActionSchedulerFinderReportOwnerNotificationScheduler::GROUP ) ) {
			\as_schedule_recurring_action(
				time() + HOUR_IN_SECONDS,
				HOUR_IN_SECONDS,
				self::RECOVERY_HOOK,
				array(),
				ActionSchedulerFinderReportOwnerNotificationScheduler::GROUP,
				true,
				20
			);
		}
	}
}
