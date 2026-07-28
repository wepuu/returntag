<?php
/**
 * Request-scoped Batch CSV download store.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

/**
 * Keeps private download carriers outside REST response normalization.
 */
final class BatchCsvDownloadStore {
	/**
	 * Downloads awaiting the REST serving phase.
	 *
	 * @var array<string, BatchCsvDownload>
	 */
	private array $downloads = array();

	/**
	 * Attach one prepared download to its immutable export identity.
	 *
	 * @param int              $batch_id Batch identifier.
	 * @param int              $export_version Export version.
	 * @param BatchCsvDownload $download Prepared download.
	 */
	public function attach(
		int $batch_id,
		int $export_version,
		BatchCsvDownload $download
	): void {
		$this->downloads[ $this->key( $batch_id, $export_version ) ] = $download;
	}

	/**
	 * Remove and return one prepared download.
	 *
	 * @param int $batch_id Batch identifier.
	 * @param int $export_version Export version.
	 */
	public function take( int $batch_id, int $export_version ): ?BatchCsvDownload {
		$key      = $this->key( $batch_id, $export_version );
		$download = $this->downloads[ $key ] ?? null;

		unset( $this->downloads[ $key ] );

		return $download;
	}

	/**
	 * Build one collision-free key for a committed export version.
	 *
	 * @param int $batch_id Batch identifier.
	 * @param int $export_version Export version.
	 */
	private function key( int $batch_id, int $export_version ): string {
		return $batch_id . ':' . $export_version;
	}
}
