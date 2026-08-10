<?php
/**
 * Composed Finder Report runtime services.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\FinderReport;

use ReturnTag\TagCore\Application\FinderReport\CleanupFinderReportEvidence;
use ReturnTag\TagCore\Application\FinderReport\ConvergeStaleFinderReportNotifications;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceSafetyAvailability;
use ReturnTag\TagCore\Application\FinderReport\FinderEmailVerification;
use ReturnTag\TagCore\Application\FinderReport\DispatchFinderEmailOtp;
use ReturnTag\TagCore\Application\FinderReport\NotifyFinderReportOwner;
use ReturnTag\TagCore\Application\FinderReport\ProcessFinderReportEvidence;
use ReturnTag\TagCore\Application\FinderReport\SubmitFinderReport;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportMediaRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportRepository;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerFinderReportOwnerNotificationScheduler;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerFinderReportProcessingScheduler;
use ReturnTag\TagCore\Application\Conversation\EnsureConversationAccess;

/** Shares one consistent composition across public and background adapters. */
final readonly class FinderReportRuntime {
	/**
	 * Create the runtime container.
	 *
	 * @param SubmitFinderReport                                    $submit Intake use case.
	 * @param ProcessFinderReportEvidence                           $process Processing use case.
	 * @param CleanupFinderReportEvidence                           $cleanup Cleanup use case.
	 * @param FinderReportMediaRepository                           $media Media persistence.
	 * @param FinderReportRepository                                $reports Report persistence.
	 * @param ActionSchedulerFinderReportProcessingScheduler        $scheduler Scheduler.
	 * @param NotifyFinderReportOwner                               $notify Owner notification use case.
	 * @param ConvergeStaleFinderReportNotifications                $converge Notification convergence.
	 * @param ActionSchedulerFinderReportOwnerNotificationScheduler $notification_scheduler Notification scheduler.
	 * @param FinderEvidenceSafetyAvailability                      $safety Safety availability.
	 * @param FinderEmailVerification|null                          $email_verification Optional Stage 5 workflow.
	 * @param DispatchFinderEmailOtp|null                           $email_dispatch Optional Stage 5 Worker.
	 * @param EnsureConversationAccess|null                         $ensure_conversation_access Optional Stage 6 access trigger.
	 */
	public function __construct(
		public SubmitFinderReport $submit,
		public ProcessFinderReportEvidence $process,
		public CleanupFinderReportEvidence $cleanup,
		public FinderReportMediaRepository $media,
		public FinderReportRepository $reports,
		public ActionSchedulerFinderReportProcessingScheduler $scheduler,
		public NotifyFinderReportOwner $notify,
		public ConvergeStaleFinderReportNotifications $converge,
		public ActionSchedulerFinderReportOwnerNotificationScheduler $notification_scheduler,
		public FinderEvidenceSafetyAvailability $safety,
		public ?FinderEmailVerification $email_verification = null,
		public ?DispatchFinderEmailOtp $email_dispatch = null,
		public ?EnsureConversationAccess $ensure_conversation_access = null
	) {
	}
}
