<?php
/**
 * Server-inspected Finder evidence metadata.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDigest;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceMime;

/** Holds only validated source facts needed before asynchronous processing. */
final readonly class FinderEvidenceSourceMetadata {
	/**
	 * Create source metadata.
	 *
	 * @param FinderEvidenceMime $mime Canonical detected MIME.
	 * @param int                $byte_count Source byte count.
	 * @param int                $width Decoded width.
	 * @param int                $height Decoded height.
	 * @param MediaDigest        $sha256 Source digest.
	 * @throws InvalidArgumentException When metadata exceeds the frozen bounds.
	 */
	public function __construct(
		public FinderEvidenceMime $mime,
		public int $byte_count,
		public int $width,
		public int $height,
		public MediaDigest $sha256
	) {
		if (
			$this->byte_count < 1
			|| $this->byte_count > FinderEvidenceSource::MAXIMUM_BYTES
			|| $this->width < 1
			|| $this->height < 1
			|| $this->width > 20000000
			|| $this->width > intdiv( 20000000, $this->height )
		) {
			throw new InvalidArgumentException( 'Finder evidence metadata is invalid.' );
		}
	}
}
