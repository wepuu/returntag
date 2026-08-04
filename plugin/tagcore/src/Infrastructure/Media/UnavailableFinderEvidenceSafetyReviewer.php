<?php
/**
 * Default-deny Finder evidence safety adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Media;

use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceDerivative;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceSafetyReviewer;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceSafetyUnavailableException;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceSafetyDecision;

/**
 * Ensures missing provider configuration can never approve an image.
 */
final class UnavailableFinderEvidenceSafetyReviewer implements FinderEvidenceSafetyReviewer {
	/**
	 * Refuse to issue a safety decision.
	 *
	 * @param FinderEvidenceDerivative $review_derivative Metadata-free review derivative.
	 * @throws FinderEvidenceSafetyUnavailableException Always.
	 */
	public function review( FinderEvidenceDerivative $review_derivative ): FinderEvidenceSafetyDecision {
		unset( $review_derivative );

		throw new FinderEvidenceSafetyUnavailableException( 'Finder evidence safety review is unavailable.' );
	}
}
