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
	private static array $downloads = array();

	/**
	 * Attach one prepared download and return an opaque one-time key.
	 *
	 * @param BatchCsvDownload $download Prepared download.
	 */
	public function attach( BatchCsvDownload $download ): string {
		do {
			$key = bin2hex( random_bytes( 16 ) );
		} while ( isset( self::$downloads[ $key ] ) );

		self::$downloads[ $key ] = $download;

		return $key;
	}

	/**
	 * Remove and return one prepared download.
	 *
	 * @param string $key Opaque one-time key.
	 */
	public function take( string $key ): ?BatchCsvDownload {
		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/D', $key ) ) {
			return null;
		}

		$download = self::$downloads[ $key ] ?? null;

		unset( self::$downloads[ $key ] );

		return $download;
	}
}
