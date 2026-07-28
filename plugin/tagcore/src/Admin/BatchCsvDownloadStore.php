<?php
/**
 * Request-scoped Batch CSV download store.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use WeakMap;
use WP_REST_Request;

/**
 * Keeps private download carriers outside REST response-data normalization.
 */
final class BatchCsvDownloadStore {
	/**
	 * Downloads awaiting the REST serving phase.
	 *
	 * @var WeakMap<WP_REST_Request, BatchCsvDownload>
	 */
	private WeakMap $downloads;

	/**
	 * Create an empty request-scoped store.
	 */
	public function __construct() {
		$this->downloads = new WeakMap();
	}

	/**
	 * Attach one prepared download to its exact REST request.
	 *
	 * @param WP_REST_Request  $request REST request.
	 * @param BatchCsvDownload $download Prepared download.
	 */
	public function attach( WP_REST_Request $request, BatchCsvDownload $download ): void {
		$this->downloads[ $request ] = $download;
	}

	/**
	 * Remove and return one prepared download.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function take( WP_REST_Request $request ): ?BatchCsvDownload {
		$download = $this->downloads[ $request ] ?? null;

		unset( $this->downloads[ $request ] );

		return $download;
	}
}
