<?php
/**
 * Submit a one-way Finder Report.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use DateInterval;
use InvalidArgumentException;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewFinderReportMediaRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewFinderReportRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportMediaRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Application\PublicTag\PublicTagStateReader;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderReportStatus;
use ReturnTag\TagCore\Domain\FinderReport\PrivateMediaObjectKind;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use Throwable;

/** Persists a private report before scheduling asynchronous processing. */
final readonly class SubmitFinderReport {
	/**
	 * Create the use case.
	 *
	 * @param PublicTagStateReader             $tags Public Tag state reader.
	 * @param FeatureFlagReader                $feature_flags Operational controls.
	 * @param FinderEvidenceSafetyAvailability $safety Safety availability.
	 * @param FinderReportRateLimiter          $rate_limiter Abuse limiter.
	 * @param FinderEvidenceSourceInspector    $inspector Source inspector.
	 * @param PrivateMediaStorage              $storage Private storage.
	 * @param FinderReportMessageProtector     $messages Message encryption.
	 * @param FinderReportRepository           $reports Report persistence.
	 * @param FinderReportMediaRepository      $media Media persistence.
	 * @param EventRepository                  $events Audit Events.
	 * @param TransactionManager               $transactions Atomic boundary.
	 * @param FinderReportProcessingScheduler  $scheduler Background scheduler.
	 * @param Clock                            $clock UTC clock.
	 */
	public function __construct(
		private PublicTagStateReader $tags,
		private FeatureFlagReader $feature_flags,
		private FinderEvidenceSafetyAvailability $safety,
		private FinderReportRateLimiter $rate_limiter,
		private FinderEvidenceSourceInspector $inspector,
		private PrivateMediaStorage $storage,
		private FinderReportMessageProtector $messages,
		private FinderReportRepository $reports,
		private FinderReportMediaRepository $media,
		private EventRepository $events,
		private TransactionManager $transactions,
		private FinderReportProcessingScheduler $scheduler,
		private Clock $clock
	) {
	}

	/**
	 * Submit one report without exposing persistence or storage details.
	 *
	 * @param FinderReportSubmissionInput $input Validated transport input.
	 * @throws FinderReportSubmissionException When intake fails closed.
	 */
	public function execute( FinderReportSubmissionInput $input ): FinderReportSubmissionResult {
		$now     = $this->clock->now();
		$message = trim( str_replace( array( "\r\n", "\r" ), "\n", $input->message ) );

		if ( '' !== $message ) {
			$length = $this->text_length( $message );

			if ( 10 > $length || 500 < $length || str_contains( $message, '<' ) || str_contains( $message, '>' ) ) {
				throw new FinderReportSubmissionException( 'Finder Report submission is invalid.' );
			}
		}

		if (
			! $this->feature_flags->is_enabled( FeatureFlag::FINDER_CONTACT )
			|| ! $this->feature_flags->is_enabled( FeatureFlag::FINDER_EVIDENCE )
			|| ! $this->safety->is_available()
		) {
			throw new FinderReportSubmissionException( 'Finder Report submission is unavailable.' );
		}

		$tag = $this->tags->find( $input->tag_id );

		if ( null === $tag || TagStatus::ACTIVE !== $tag->tag_status || null === $tag->owner_id ) {
			throw new FinderReportSubmissionException( 'Finder Report submission is unavailable.' );
		}

		if ( ! $this->rate_limiter->reserve( $input->tag_id, $input->peer_lookup, $input->risk_lookup, $now ) ) {
			throw new FinderReportSubmissionException( 'Finder Report submission is unavailable.' );
		}

		try {
			$metadata = $this->inspector->inspect( $input->evidence );
			$source   = $this->storage->put( PrivateMediaObjectKind::SOURCE, $input->evidence->bytes );
		} catch ( Throwable $exception ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Wrapped, never rendered.
			throw new FinderReportSubmissionException( 'Finder Report submission is unavailable.', 0, $exception );
		}

		try {
			$report = $this->transactions->transactional(
				function () use ( $input, $message, $tag, $metadata, $source, $now ) {
					$stored_report = $this->reports->insert(
						new NewFinderReportRecord(
							$input->tag_id->value,
							$tag->owner_id,
							'' === $message ? null : $this->messages->encrypt( $message, $input->tag_id ),
							FinderReportStatus::RECEIVED,
							FinderEvidenceStatus::QUARANTINED,
							null,
							null,
							$now->add( new DateInterval( 'PT24H' ) ),
							$now,
							$now
						)
					);

					$this->media->insert(
						new NewFinderReportMediaRecord(
							$stored_report->finder_report_id,
							$source->reference_ciphertext,
							$source->encryption_key_id,
							$metadata->sha256,
							$metadata->mime,
							$metadata->byte_count,
							$metadata->width,
							$metadata->height,
							null,
							null,
							FinderEvidenceStatus::QUARANTINED,
							$now->add( new DateInterval( 'PT24H' ) ),
							$now,
							$now
						)
					);

					$this->events->append(
						new NewEventRecord(
							'finder_report_submitted',
							'system',
							null,
							'finder_report',
							(string) $stored_report->finder_report_id,
							'accepted',
							null,
							EventMetadata::none(),
							$now
						)
					);

					return $stored_report;
				}
			);
		} catch ( Throwable $exception ) {
			try {
				$this->storage->delete( PrivateMediaObjectKind::SOURCE, $source );
			} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Recovery worker owns failed compensation.
				// A bounded cleanup worker remains authoritative for a failed compensation.
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Wrapped, never rendered.
			throw new FinderReportSubmissionException( 'Finder Report submission is unavailable.', 0, $exception );
		}

		try {
			$this->scheduler->schedule( $report->finder_report_id );
		} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Recovery hook owns scheduling gaps.
			// The recovery hook scans quarantined rows; durable intake remains accepted.
		}

		return new FinderReportSubmissionResult( $report->finder_report_id );
	}

	/**
	 * Count Unicode code points without requiring mbstring.
	 *
	 * @param string $value Validated text.
	 * @throws InvalidArgumentException When text is invalid UTF-8.
	 */
	private function text_length( string $value ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value, 'UTF-8' );
		}

		$count = preg_match_all( '/./us', $value );

		if ( false === $count ) {
			throw new InvalidArgumentException( 'Finder Report message is invalid.' );
		}

		return $count;
	}
}
