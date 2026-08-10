<?php
/**
 * Public Finder Report form boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceSafetyAvailability;
use ReturnTag\TagCore\Application\FinderReport\FinderReportProcessingScheduler;
use ReturnTag\TagCore\Application\FinderReport\FinderReportSubmissionInput;
use ReturnTag\TagCore\Application\FinderReport\SubmitFinderReport;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Infrastructure\Security\WordPressPublicRequestHasher;
use Throwable;

/** Validates one anonymous same-site submission without collecting Finder identity. */
final readonly class FinderReportFormHandler {
	public const NONCE_ACTION  = 'returntag_submit_finder_report';
	public const NONCE_FIELD   = 'returntag_finder_nonce';
	public const ACTION_FIELD  = 'returntag_finder_action';
	public const SUBMIT_ACTION = 'submit_report';
	public const MESSAGE_FIELD = 'returntag_finder_message';
	public const PHOTO_FIELD   = 'returntag_finder_photo';
	public const TOKEN_FIELD   = 'returntag_finder_submission_token';

	/**
	 * Create the boundary.
	 *
	 * @param SubmitFinderReport|null               $submit Configured intake use case.
	 * @param FinderEvidenceSafetyAvailability|null $safety Safety-provider availability.
	 * @param FinderReportProcessingScheduler|null  $scheduler Durable queue boundary.
	 * @param FeatureFlagReader                     $feature_flags Operational controls.
	 * @param PublicFormRequestGuard                $request_guard Browser request guard.
	 * @param FinderEvidenceUploadReader            $uploads Trusted upload reader.
	 * @param FinderReportSubmissionLedger          $ledger Idempotency ledger.
	 * @param WordPressPublicRequestHasher          $hasher Privacy-safe request hasher.
	 */
	public function __construct(
		private ?SubmitFinderReport $submit,
		private ?FinderEvidenceSafetyAvailability $safety,
		private ?FinderReportProcessingScheduler $scheduler,
		private FeatureFlagReader $feature_flags,
		private PublicFormRequestGuard $request_guard,
		private FinderEvidenceUploadReader $uploads,
		private FinderReportSubmissionLedger $ledger,
		private WordPressPublicRequestHasher $hasher
	) {
	}

	/** Check all fail-closed runtime controls before rendering intake. */
	public function is_available(): bool {
		return null !== $this->submit
			&& null !== $this->safety
			&& null !== $this->scheduler
			&& $this->safety->is_available()
			&& $this->scheduler->is_available()
			&& $this->feature_flags->is_enabled( FeatureFlag::FINDER_CONTACT )
			&& $this->feature_flags->is_enabled( FeatureFlag::FINDER_EVIDENCE );
	}

	/** Determine whether this POST belongs to Finder Report intake. */
	public function is_submission_action(): bool {
		return self::SUBMIT_ACTION === $this->request_guard->post_string( self::ACTION_FIELD, 32 );
	}

	/**
	 * Issue a fresh form token only for an available runtime.
	 *
	 * @param TagId $tag_id Server-resolved Tag.
	 */
	public function issue_token( TagId $tag_id ): string {
		return $this->is_available() ? $this->ledger->issue( $tag_id ) : '';
	}

	/**
	 * Validate and submit exactly one message/photo report.
	 *
	 * @param TagId $tag_id Server-resolved Tag.
	 */
	public function submit( TagId $tag_id ): FinderReportFormState {
		if ( ! $this->is_available() || ! $this->is_submission_action() ) {
			return FinderReportFormState::ERROR;
		}

		if ( ! $this->request_guard->is_same_site() || ! $this->request_guard->valid_nonce( self::NONCE_FIELD, self::NONCE_ACTION ) ) {
			return FinderReportFormState::ERROR;
		}

		$token = $this->request_guard->post_string( self::TOKEN_FIELD, 160 );

		if ( '' === $token ) {
			return FinderReportFormState::ERROR;
		}

		$claim = $this->ledger->claim( $tag_id, $token );

		if ( FinderReportSubmissionClaim::REPLAYED === $claim ) {
			return FinderReportFormState::ACCEPTED;
		}

		if ( FinderReportSubmissionClaim::CLAIMED !== $claim ) {
			return FinderReportFormState::ERROR;
		}

		$message = trim( $this->request_guard->post_string( self::MESSAGE_FIELD, 2000 ) );

		if ( '' !== $message && ! $this->valid_message( $message ) ) {
			$this->ledger->release( $tag_id, $token );

			return FinderReportFormState::INVALID_MESSAGE;
		}

		$photo = $this->uploads->read( self::PHOTO_FIELD );

		if ( null === $photo ) {
			$this->ledger->release( $tag_id, $token );

			return FinderReportFormState::INVALID_PHOTO;
		}

		try {
			$ip     = $this->request_guard->direct_peer_ip();
			$result = $this->submit?->execute(
				new FinderReportSubmissionInput(
					$tag_id,
					$message,
					$photo,
					$this->hasher->finder_peer_lookup( $ip ),
					$this->hasher->finder_risk_lookup( hash( 'sha256', $token ) )
				)
			);
			$this->ledger->complete( $tag_id, $token, $result?->finder_report_id );

			return FinderReportFormState::ACCEPTED;
		} catch ( Throwable ) {
			$this->ledger->release( $tag_id, $token );

			return FinderReportFormState::ERROR;
		}
	}

	/**
	 * Validate the optional 10–500 Unicode character plain-text contract.
	 *
	 * @param string $message Normalized message.
	 */
	private function valid_message( string $message ): bool {
		if ( wp_strip_all_tags( $message ) !== $message ) {
			return false;
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $message, 'UTF-8' ) : preg_match_all( '/./us', $message );

		return is_int( $length ) && $length >= 10 && $length <= 500;
	}
}
