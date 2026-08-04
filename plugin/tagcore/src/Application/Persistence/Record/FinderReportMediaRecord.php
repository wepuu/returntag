<?php
/**
 * Stored Finder Report private-media record.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * One persisted private-media row.
 */
final readonly class FinderReportMediaRecord {
	/**
	 * Create one stored private-media record.
	 *
	 * @param int                        $finder_report_media_id Internal media identifier.
	 * @param NewFinderReportMediaRecord $data Stored media data.
	 */
	public function __construct(
		public int $finder_report_media_id,
		public NewFinderReportMediaRecord $data
	) {
		RecordValidator::positive_id( $this->finder_report_media_id, 'finder_report_media_id' );
	}
}
