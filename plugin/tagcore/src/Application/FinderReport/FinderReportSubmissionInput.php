<?php
/**
 * Finder Report submission input.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Domain\Tag\TagId;

/** Carries bounded public input after transport parsing. */
final readonly class FinderReportSubmissionInput {
	/**
	 * Create submission input.
	 *
	 * @param TagId                $tag_id Server-resolved Tag.
	 * @param string               $message Optional plain-text message.
	 * @param FinderEvidenceSource $evidence Required source image.
	 * @param LookupDigest         $peer_lookup Keyed peer lookup.
	 * @param LookupDigest         $risk_lookup Keyed risk lookup.
	 */
	public function __construct(
		public TagId $tag_id,
		public string $message,
		public FinderEvidenceSource $evidence,
		public LookupDigest $peer_lookup,
		public LookupDigest $risk_lookup
	) {
	}
}
