<?php
/**
 * Numbered database migration contract.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

/**
 * Defines one retry-safe schema change and its postcondition check.
 */
interface Migration {
	/**
	 * Return the positive, contiguous schema version introduced by this migration.
	 */
	public function version(): int;

	/**
	 * Return a stable operational name that contains no sensitive data.
	 */
	public function name(): string;

	/**
	 * Apply the schema change idempotently.
	 */
	public function up(): void;

	/**
	 * Verify the required schema postconditions.
	 */
	public function verify(): bool;
}
