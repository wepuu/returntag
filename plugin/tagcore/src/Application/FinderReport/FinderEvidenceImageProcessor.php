<?php
/**
 * Finder evidence image-processing port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

/**
 * Validates, decodes, strips metadata, and creates controlled derivatives.
 */
interface FinderEvidenceImageProcessor {
	/**
	 * Process one untrusted source image.
	 *
	 * @param FinderEvidenceSource $source Untrusted bounded bytes.
	 * @throws FinderEvidenceProcessingException When validation or processing fails.
	 */
	public function process( FinderEvidenceSource $source ): ProcessedFinderEvidence;
}
