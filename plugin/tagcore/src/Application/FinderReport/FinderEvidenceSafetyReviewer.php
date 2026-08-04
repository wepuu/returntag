<?php
/**
 * Finder evidence content-safety port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceSafetyDecision;

/**
 * Receives only the minimum metadata-free review derivative.
 */
interface FinderEvidenceSafetyReviewer {
	/**
	 * Review controlled evidence bytes.
	 *
	 * @param FinderEvidenceDerivative $review_derivative Metadata-free review derivative.
	 * @throws FinderEvidenceSafetyUnavailableException When no decision is available.
	 */
	public function review( FinderEvidenceDerivative $review_derivative ): FinderEvidenceSafetyDecision;
}
