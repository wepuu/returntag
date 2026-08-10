<?php
/**
 * Native WordPress Finder evidence upload reader.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceSource;
use Throwable;

/** Ignores client filenames and MIME declarations and trusts only upload bytes. */
final class WordPressFinderEvidenceUploadReader implements FinderEvidenceUploadReader {
	/**
	 * Read exactly one successful PHP upload.
	 *
	 * @param string $field Trusted upload field name.
	 */
	public function read( string $field ): ?FinderEvidenceSource {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller verifies the form nonce before reading files.
		$file = $_FILES[ $field ] ?? null;

		if (
			! is_array( $file )
			|| ! isset( $file['error'], $file['size'], $file['tmp_name'] )
			|| ! is_int( $file['error'] )
			|| UPLOAD_ERR_OK !== $file['error']
			|| ! is_int( $file['size'] )
			|| $file['size'] < 1
			|| $file['size'] > FinderEvidenceSource::MAXIMUM_BYTES
			|| ! is_string( $file['tmp_name'] )
			|| ! is_uploaded_file( $file['tmp_name'] )
		) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A verified local PHP upload is not a URL.
		$bytes = file_get_contents( $file['tmp_name'] );

		if ( false === $bytes || strlen( $bytes ) !== $file['size'] ) {
			return null;
		}

		try {
			return new FinderEvidenceSource( $bytes );
		} catch ( Throwable ) {
			return null;
		}
	}
}
