<?php
/**
 * Fail stale Finder Report notification claims closed.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use DateInterval;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;

/** Prevents automatic resend after an ambiguous Worker crash window. */
final readonly class ConvergeStaleFinderReportNotifications {
	/**
	 * Create the bounded convergence use case.
	 *
	 * @param FinderReportRepository $reports Report persistence.
	 * @param EventRepository        $events Audit Events.
	 * @param TransactionManager     $transactions Atomic database boundary.
	 * @param Clock                  $clock UTC clock.
	 */
	public function __construct(
		private FinderReportRepository $reports,
		private EventRepository $events,
		private TransactionManager $transactions,
		private Clock $clock
	) {
	}

	/**
	 * Mark at most 100 stale claims terminally failed.
	 *
	 * @param int $limit Bounded row limit.
	 */
	public function execute( int $limit = 50 ): int {
		$limit        = max( 1, min( 100, $limit ) );
		$now          = $this->clock->now();
		$stale_before = $now->sub( new DateInterval( 'PT15M' ) );
		$converged    = 0;

		foreach ( $this->reports->find_stale_owner_notification_claims( $stale_before, $limit ) as $finder_report_id ) {
			$changed = $this->transactions->transactional(
				function () use ( $finder_report_id, $now ): bool {
					if ( ! $this->reports->mark_owner_notification_failed( $finder_report_id, $now ) ) {
						return false;
					}

					$this->events->append(
						new NewEventRecord(
							'finder_report_owner_notification_failed',
							'system',
							null,
							'finder_report',
							(string) $finder_report_id,
							'failed',
							null,
							EventMetadata::none(),
							$now
						)
					);

					return true;
				}
			);

			if ( $changed ) {
				++$converged;
			}
		}

		return $converged;
	}
}
