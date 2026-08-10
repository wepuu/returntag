<?php
/**
 * Bounded Finder evidence retention cleanup.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Record\FinderReportMediaRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportMediaRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Domain\FinderReport\PrivateMediaObjectKind;
use RuntimeException;

/** Deletes object bytes first, then removes usable references and expires state. */
final readonly class CleanupFinderReportEvidence {
	/**
	 * Create the cleanup use case.
	 *
	 * @param FinderReportMediaRepository $media Media persistence.
	 * @param FinderReportRepository      $reports Report persistence.
	 * @param PrivateMediaStorage         $storage Private storage.
	 * @param EventRepository             $events Audit Events.
	 * @param TransactionManager          $transactions Atomic boundary.
	 * @param Clock                       $clock UTC clock.
	 */
	public function __construct(
		private FinderReportMediaRepository $media,
		private FinderReportRepository $reports,
		private PrivateMediaStorage $storage,
		private EventRepository $events,
		private TransactionManager $transactions,
		private Clock $clock
	) {
	}

	/**
	 * Clean at most one bounded batch and return the count.
	 *
	 * @param int $limit Bounded row limit.
	 */
	public function execute( int $limit = 50 ): int {
		$now     = $this->clock->now();
		$expired = $this->media->find_expired( $now, $limit );
		$count   = 0;

		foreach ( $expired as $record ) {
			$this->delete_objects( $record );

			$this->transactions->transactional(
				function () use ( $record, $now ): void {
					$id = $record->data->finder_report_id;

					if (
						! $this->media->mark_deleted( $id, $now )
						|| ! $this->reports->mark_expired( $id, $now )
					) {
						throw new RuntimeException( 'Finder evidence cleanup state transition failed.' );
					}

					$this->events->append(
						new NewEventRecord(
							'finder_report_expired',
							'system',
							null,
							'finder_report',
							(string) $id,
							'deleted',
							null,
							EventMetadata::none(),
							$now
						)
					);
				}
			);
			++$count;
		}

		return $count;
	}

	/**
	 * Remove source and any controlled derivatives idempotently.
	 *
	 * @param FinderReportMediaRecord $record Stored media record.
	 */
	private function delete_objects( FinderReportMediaRecord $record ): void {
		$data   = $record->data;
		$source = new PrivateMediaObject(
			$data->object_reference_ciphertext,
			$data->encryption_key_id,
			$data->content_sha256,
			$data->source_byte_count
		);
		$this->storage->delete( PrivateMediaObjectKind::SOURCE, $source );

		foreach ( array( array( PrivateMediaObjectKind::REVIEW, $data->review_derivative ), array( PrivateMediaObjectKind::EMAIL, $data->email_derivative ) ) as [ $kind, $derivative ] ) {
			if ( null !== $derivative ) {
				$this->storage->delete(
					$kind,
					new PrivateMediaObject(
						$derivative->reference_ciphertext,
						$data->encryption_key_id,
						$derivative->sha256,
						$derivative->byte_count
					)
				);
			}
		}
	}
}
