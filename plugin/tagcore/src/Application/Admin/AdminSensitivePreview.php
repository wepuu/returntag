<?php
/**
 * Fail-closed Finder Report sensitive preview use case.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\FinderReport\FinderReportMessageProtector;
use ReturnTag\TagCore\Application\FinderReport\PrivateMediaObject;
use ReturnTag\TagCore\Application\FinderReport\PrivateMediaStorage;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportMediaRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportRepository;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderReportStatus;
use ReturnTag\TagCore\Domain\FinderReport\PrivateMediaObjectKind;
use ReturnTag\TagCore\Domain\Tag\TagId;
use RuntimeException;

/** Decrypts only an eligible message or processed review derivative. */
final readonly class AdminSensitivePreview {
	/**
	 * Create the preview use case.
	 *
	 * @param FeatureFlagReader            $feature_flags Operational controls.
	 * @param FinderReportRepository       $reports Finder Report records.
	 * @param FinderReportMediaRepository  $media Private-media records.
	 * @param FinderReportMessageProtector $messages Message crypto adapter.
	 * @param PrivateMediaStorage          $storage Private-media storage.
	 * @param SensitivePreviewAudit        $audit Sensitive-view audit writer.
	 * @param Clock                        $clock UTC clock.
	 */
	public function __construct(
		private FeatureFlagReader $feature_flags,
		private FinderReportRepository $reports,
		private FinderReportMediaRepository $media,
		private FinderReportMessageProtector $messages,
		private PrivateMediaStorage $storage,
		private SensitivePreviewAudit $audit,
		private Clock $clock
	) {
	}

	/**
	 * Reveal one optional message after every policy check succeeds.
	 *
	 * @param int $finder_report_id Finder Report identifier.
	 * @param int $operator_id WordPress operator User ID.
	 * @throws RuntimeException When preview is disabled or unavailable.
	 */
	public function reveal_message( int $finder_report_id, int $operator_id ): string {
		$this->assert_enabled();
		$report = $this->reports->find_by_id( $finder_report_id );
		$now    = $this->clock->now();

		if (
			null === $report
			|| ! $this->is_previewable( $report->data->report_status )
			|| $report->data->expires_at <= $now
			|| null === $report->data->message_ciphertext
		) {
			throw new RuntimeException( 'Sensitive preview is unavailable.' );
		}

		$message = $this->messages->decrypt( $report->data->message_ciphertext, TagId::from_canonical( $report->data->tag_id ) );
		$this->audit->record( 'finder_report_message_viewed', $operator_id, $finder_report_id, $now );

		return $message;
	}

	/**
	 * Reveal only the retained, processed review derivative.
	 *
	 * @param int $finder_report_id Finder Report identifier.
	 * @param int $operator_id WordPress operator User ID.
	 * @throws RuntimeException When preview is disabled or unavailable.
	 */
	public function reveal_evidence( int $finder_report_id, int $operator_id ): string {
		$this->assert_enabled();
		$report = $this->reports->find_by_id( $finder_report_id );
		$media  = $this->media->find_by_report_id( $finder_report_id );
		$now    = $this->clock->now();

		if (
			null === $report
			|| ! $this->is_previewable( $report->data->report_status )
			|| $report->data->expires_at <= $now
			|| null === $media
			|| FinderEvidenceStatus::READY !== $media->data->media_status
			|| $media->data->retention_until <= $now
			|| null === $media->data->review_derivative
		) {
			throw new RuntimeException( 'Sensitive preview is unavailable.' );
		}

		$review = $media->data->review_derivative;
		$bytes  = $this->storage->read(
			PrivateMediaObjectKind::REVIEW,
			new PrivateMediaObject( $review->reference_ciphertext, $media->data->encryption_key_id, $review->sha256, $review->byte_count )
		);
		$this->audit->record( 'finder_report_evidence_viewed', $operator_id, $finder_report_id, $now );

		return $bytes;
	}

	/**
	 * Decide whether the report lifecycle still permits internal review.
	 *
	 * @param FinderReportStatus $status Persisted Finder Report status.
	 */
	private function is_previewable( FinderReportStatus $status ): bool {
		return in_array(
			$status,
			array(
				FinderReportStatus::RECEIVED,
				FinderReportStatus::PROCESSING,
				FinderReportStatus::READY,
				FinderReportStatus::NOTIFIED,
			),
			true
		);
	}

	/**
	 * Require the independent sensitive-preview control.
	 *
	 * @throws RuntimeException When the control is disabled.
	 */
	private function assert_enabled(): void {
		if ( ! $this->feature_flags->is_enabled( FeatureFlag::ADMIN_SENSITIVE_PREVIEW ) ) {
			throw new RuntimeException( 'Sensitive preview is unavailable.' );
		}
	}
}
