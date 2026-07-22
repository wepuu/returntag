<?php
/**
 * Schema version persistence contract.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

/**
 * Reads and advances the site-scoped schema version.
 */
interface SchemaVersionStore {
	/**
	 * Return the last successfully applied schema version.
	 */
	public function current_version(): int;

	/**
	 * Persist one successfully verified migration version.
	 *
	 * @param int $version Applied migration version.
	 */
	public function mark_applied( int $version ): void;
}
