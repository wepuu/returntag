<?php
/**
 * Safety-approved Finder evidence marker.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

/**
 * Can only be created by the fail-closed review use case.
 */
final readonly class ApprovedFinderEvidence {
	/**
	 * Create an approved marker.
	 *
	 * @param ProcessedFinderEvidence $evidence Processed evidence.
	 */
	public function __construct( public ProcessedFinderEvidence $evidence ) {
	}
}
