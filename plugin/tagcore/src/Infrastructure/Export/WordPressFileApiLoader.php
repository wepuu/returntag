<?php
/**
 * WordPress file API loader.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Export;

use ReturnTag\TagCore\Application\Batch\Exception\BatchExportArtifactFailure;

/**
 * Loads the wp-admin file helpers when execution begins through REST.
 */
final class WordPressFileApiLoader {
	/**
	 * Ensure wp_tempnam() is available in every supported WordPress runtime.
	 *
	 * WordPress does not load wp-admin/includes/file.php for REST requests.
	 *
	 * @throws BatchExportArtifactFailure When the trusted core file API is unavailable.
	 */
	public function ensure_loaded(): void {
		if ( function_exists( 'wp_tempnam' ) ) {
			return;
		}

		$file_api = ABSPATH . 'wp-admin/includes/file.php';

		if ( ! is_file( $file_api ) ) {
			throw new BatchExportArtifactFailure( 'Batch export artifact could not be prepared.' );
		}

		require_once $file_api;
	}
}
