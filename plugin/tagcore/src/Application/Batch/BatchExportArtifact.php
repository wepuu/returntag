<?php
/**
 * Prepared Batch export artifact contract.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

/**
 * Represents one private temporary export artifact.
 */
interface BatchExportArtifact {
	/**
	 * Return the exported data-row count, excluding the header.
	 */
	public function row_count(): int;

	/**
	 * Return the lowercase SHA-256 digest of the exact file bytes.
	 */
	public function checksum(): string;

	/**
	 * Return the exact artifact byte size.
	 */
	public function byte_size(): int;

	/**
	 * Stream the artifact to the current response.
	 */
	public function stream(): void;

	/**
	 * Remove the temporary artifact.
	 */
	public function cleanup(): void;
}
