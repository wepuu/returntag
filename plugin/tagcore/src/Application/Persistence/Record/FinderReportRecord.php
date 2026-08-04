<?php
/**
 * Stored Finder Report record.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * One persisted one-way Finder Report row.
 */
final readonly class FinderReportRecord {
	/**
	 * Create one stored Finder Report record.
	 *
	 * @param int                   $finder_report_id Internal report identifier.
	 * @param NewFinderReportRecord $data Stored report data.
	 */
	public function __construct(
		public int $finder_report_id,
		public NewFinderReportRecord $data
	) {
		RecordValidator::positive_id( $this->finder_report_id, 'finder_report_id' );
	}
}
