<?php
/**
 * Processed Finder evidence result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDigest;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceMime;

/**
 * Holds source metadata and controlled derivatives without identity data.
 */
final readonly class ProcessedFinderEvidence {
	/**
	 * Create a processed result.
	 *
	 * @param FinderEvidenceMime       $source_mime Server-detected source MIME.
	 * @param int                      $source_byte_count Source byte count.
	 * @param int                      $source_width Decoded source width.
	 * @param int                      $source_height Decoded source height.
	 * @param MediaDigest              $source_sha256 Source integrity digest.
	 * @param FinderEvidenceDerivative $review Review derivative.
	 * @param FinderEvidenceDerivative $email Email derivative.
	 * @throws InvalidArgumentException When source metadata exceeds frozen bounds.
	 */
	public function __construct(
		public FinderEvidenceMime $source_mime,
		public int $source_byte_count,
		public int $source_width,
		public int $source_height,
		public MediaDigest $source_sha256,
		public FinderEvidenceDerivative $review,
		public FinderEvidenceDerivative $email
	) {
		if (
			$this->source_byte_count < 1
			|| $this->source_byte_count > FinderEvidenceSource::MAXIMUM_BYTES
			|| $this->source_width < 1
			|| $this->source_height < 1
			|| $this->source_width > 20000000
			|| $this->source_width > intdiv( 20000000, $this->source_height )
		) {
			throw new InvalidArgumentException( 'Processed Finder evidence is invalid.' );
		}
	}
}
