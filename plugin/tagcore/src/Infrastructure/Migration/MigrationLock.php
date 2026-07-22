<?php
/**
 * Migration lock contract.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

/**
 * Serializes schema changes for one WordPress site.
 */
interface MigrationLock {
	/**
	 * Attempt to obtain exclusive ownership.
	 */
	public function acquire(): bool;

	/**
	 * Release ownership when held by this instance.
	 */
	public function release(): void;
}
