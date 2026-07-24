<?php
/**
 * Stored Tag record.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

/**
 * One persisted Tag row.
 */
final readonly class TagRecord {
	/**
	 * Create a stored Tag record.
	 *
	 * @param NewTagRecord $data Stored Tag data.
	 */
	public function __construct( public NewTagRecord $data ) {
	}
}
