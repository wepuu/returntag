<?php
/**
 * Finder evidence intake inspection port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

/** Performs bounded server inspection before private quarantine. */
interface FinderEvidenceSourceInspector {
	/**
	 * Inspect one bounded source without trusting browser metadata.
	 *
	 * @param FinderEvidenceSource $source Untrusted bounded bytes.
	 */
	public function inspect( FinderEvidenceSource $source ): FinderEvidenceSourceMetadata;
}
