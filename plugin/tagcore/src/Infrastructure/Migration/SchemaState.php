<?php
/**
 * Read-only schema readiness state.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

/**
 * Exposes current and target versions for future fail-closed startup checks.
 */
final class SchemaState {
	/**
	 * Create read-only schema state.
	 *
	 * @param SchemaVersionStore $version_store Site-scoped version store.
	 * @param MigrationRegistry  $registry      Validated migration registry.
	 */
	public function __construct(
		private readonly SchemaVersionStore $version_store,
		private readonly MigrationRegistry $registry
	) {
	}

	/**
	 * Return the last successfully applied schema version.
	 */
	public function current_version(): int {
		return $this->version_store->current_version();
	}

	/**
	 * Return the highest migration version supported by this code.
	 */
	public function target_version(): int {
		return $this->registry->target_version();
	}

	/**
	 * Determine whether business startup may safely use the current schema.
	 */
	public function is_current(): bool {
		return $this->current_version() === $this->target_version();
	}
}
