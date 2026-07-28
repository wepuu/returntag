<?php
/**
 * Stub Batch export artifact fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture;

use ReturnTag\TagCore\Application\Batch\BatchExportArtifact;

/**
 * Exposes fixed audited file properties.
 */
final class StubBatchExportArtifact implements BatchExportArtifact {
	/**
	 * Whether cleanup was requested.
	 *
	 * @var bool
	 */
	public bool $cleaned = false;

	/**
	 * Create the artifact.
	 *
	 * @param int    $rows Data-row count.
	 * @param string $digest SHA-256 digest.
	 */
	public function __construct(
		private readonly int $rows,
		private readonly string $digest
	) {
	}

	/**
	 * Return the fixed row count.
	 */
	public function row_count(): int {
		return $this->rows;
	}

	/**
	 * Return the fixed digest.
	 */
	public function checksum(): string {
		return $this->digest;
	}

	/**
	 * Return the fixed byte size.
	 */
	public function byte_size(): int {
		return 100;
	}

	/**
	 * Emit fixed test bytes.
	 */
	public function stream(): void {
		echo 'csv'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test artifact.
	}

	/**
	 * Record cleanup.
	 */
	public function cleanup(): void {
		$this->cleaned = true;
	}
}
