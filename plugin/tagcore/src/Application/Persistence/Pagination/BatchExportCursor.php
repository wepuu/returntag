<?php
/**
 * Stable Batch Export cursor.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Pagination;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * Orders export history by descending version.
 */
final readonly class BatchExportCursor {
	/**
	 * Create an export cursor.
	 *
	 * @param int $export_version Last export version.
	 */
	public function __construct( public int $export_version ) {
		RecordValidator::positive_id( $this->export_version, 'export_version' );
	}
}
