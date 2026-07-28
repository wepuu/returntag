<?php
/**
 * Private temporary Batch export artifact.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Export;

use ReturnTag\TagCore\Application\Batch\BatchExportArtifact;
use ReturnTag\TagCore\Application\Batch\Exception\BatchExportArtifactFailure;

/**
 * Streams and removes one temporary file without exposing its path.
 */
final class TemporaryBatchExportArtifact implements BatchExportArtifact {
	/**
	 * Whether cleanup has already completed.
	 *
	 * @var bool
	 */
	private bool $cleaned = false;

	/**
	 * Create an artifact.
	 *
	 * @param string $path Private temporary path.
	 * @param int    $row_count Data-row count.
	 * @param string $checksum Lowercase SHA-256.
	 * @param int    $byte_size Exact file size.
	 */
	public function __construct(
		private readonly string $path,
		private readonly int $row_count,
		private readonly string $checksum,
		private readonly int $byte_size
	) {
	}

	/**
	 * Remove abandoned artifacts.
	 */
	public function __destruct() {
		$this->cleanup();
	}

	/**
	 * Return the data-row count.
	 */
	public function row_count(): int {
		return $this->row_count;
	}

	/**
	 * Return the exact SHA-256 digest.
	 */
	public function checksum(): string {
		return $this->checksum;
	}

	/**
	 * Return the exact byte size.
	 */
	public function byte_size(): int {
		return $this->byte_size;
	}

	/**
	 * Stream the file in bounded chunks.
	 *
	 * @throws BatchExportArtifactFailure When the artifact cannot be streamed.
	 */
	public function stream(): void {
		if ( $this->cleaned ) {
			throw new BatchExportArtifactFailure( 'Batch export artifact is unavailable.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Private temporary artifact requires a read-only stream.
		$handle = fopen( $this->path, 'rb' );

		if ( false === $handle ) {
			throw new BatchExportArtifactFailure( 'Batch export artifact is unavailable.' );
		}

		try {
			while ( ! feof( $handle ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Bounded streaming avoids loading the complete export.
				$chunk = fread( $handle, 65536 );

				if ( false === $chunk ) {
					throw new BatchExportArtifactFailure( 'Batch export artifact could not be delivered.' );
				}

				echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Exact audited CSV bytes.
			}
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the exact private read stream.
			$this->cleanup();
		}
	}

	/**
	 * Remove the private temporary artifact once.
	 */
	public function cleanup(): void {
		if ( $this->cleaned ) {
			return;
		}

		$this->cleaned = true;

		if ( is_file( $this->path ) ) {
			wp_delete_file( $this->path );
		}
	}
}
