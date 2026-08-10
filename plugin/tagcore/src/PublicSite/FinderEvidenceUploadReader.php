<?php
/**
 * Finder evidence upload boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceSource;

/** Reads exactly one trusted PHP upload into a bounded domain value. */
interface FinderEvidenceUploadReader {
	/**
	 * Read one upload, or return null for every invalid transport shape.
	 *
	 * @param string $field Trusted upload field name.
	 */
	public function read( string $field ): ?FinderEvidenceSource;
}
