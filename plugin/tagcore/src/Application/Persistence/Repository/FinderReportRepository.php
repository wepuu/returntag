<?php
/**
 * Finder Report persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Repository;

use ReturnTag\TagCore\Application\Persistence\Record\FinderReportRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewFinderReportRecord;

/**
 * Narrow one-way Finder Report persistence contract.
 */
interface FinderReportRepository {
	/**
	 * Insert one Finder Report.
	 *
	 * @param NewFinderReportRecord $record New report data.
	 */
	public function insert( NewFinderReportRecord $record ): FinderReportRecord;

	/**
	 * Find one Finder Report by internal identifier.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 */
	public function find_by_id( int $finder_report_id ): ?FinderReportRecord;
}
