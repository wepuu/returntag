<?php
/**
 * Finder Report private-media persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Repository;

use ReturnTag\TagCore\Application\Persistence\Record\FinderReportMediaRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewFinderReportMediaRecord;

/**
 * Narrow private-media metadata persistence contract.
 */
interface FinderReportMediaRepository {
	/**
	 * Insert one private-media record.
	 *
	 * @param NewFinderReportMediaRecord $record New media data.
	 */
	public function insert( NewFinderReportMediaRecord $record ): FinderReportMediaRecord;

	/**
	 * Find the unique media record for one Finder Report.
	 *
	 * @param int $finder_report_id Parent report identifier.
	 */
	public function find_by_report_id( int $finder_report_id ): ?FinderReportMediaRecord;
}
