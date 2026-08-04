<?php
/**
 * Fail-closed Finder evidence content-safety use case.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceSafetyDecision;

/**
 * Creates an approved marker only after an explicit provider approval.
 */
final readonly class ReviewFinderEvidence {
	/**
	 * Create the review use case.
	 *
	 * @param FinderEvidenceSafetyReviewer $reviewer Approved provider adapter.
	 */
	public function __construct( private FinderEvidenceSafetyReviewer $reviewer ) {
	}

	/**
	 * Review one processed image.
	 *
	 * @param ProcessedFinderEvidence $evidence Processed evidence.
	 * @throws FinderEvidenceRejectedException When the provider rejects the derivative.
	 */
	public function review( ProcessedFinderEvidence $evidence ): ApprovedFinderEvidence {
		$decision = $this->reviewer->review( $evidence->review );

		if ( FinderEvidenceSafetyDecision::APPROVED !== $decision ) {
			throw new FinderEvidenceRejectedException( 'Finder evidence was not approved.' );
		}

		return new ApprovedFinderEvidence( $evidence );
	}
}
