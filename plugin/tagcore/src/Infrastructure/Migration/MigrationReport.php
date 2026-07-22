<?php
/**
 * Safe migration execution report.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

/**
 * Records version progress without SQL, credentials, or product data.
 */
final class MigrationReport {
	/**
	 * Create a safe migration progress report.
	 *
	 * @param int   $starting_version Initial schema version.
	 * @param int   $ending_version   Final schema version.
	 * @param array $applied_versions Versions applied by this run.
	 * @phpstan-param list<int> $applied_versions
	 */
	public function __construct(
		public readonly int $starting_version,
		public readonly int $ending_version,
		public readonly array $applied_versions
	) {
	}
}
