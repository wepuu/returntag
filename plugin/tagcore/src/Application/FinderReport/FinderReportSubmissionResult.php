<?php
/**
 * Finder Report submission result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use InvalidArgumentException;

/** Carries only the internal report identifier to trusted adapters. */
final readonly class FinderReportSubmissionResult {
	/**
	 * Create a result.
	 *
	 * @param int $finder_report_id Positive internal report identifier.
	 * @throws InvalidArgumentException When the identifier is invalid.
	 */
	public function __construct( public int $finder_report_id ) {
		if ( $this->finder_report_id < 1 ) {
			throw new InvalidArgumentException( 'Finder Report identifier is invalid.' );
		}
	}
}
