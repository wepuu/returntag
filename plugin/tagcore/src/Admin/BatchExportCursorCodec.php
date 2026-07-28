<?php
/**
 * Batch export history REST cursor codec.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Pagination\BatchExportCursor;

/**
 * Keeps the internal export keyset out of the REST shape.
 */
final class BatchExportCursorCodec {
	private const PREFIX = 'rt207:';

	/**
	 * Encode one versioned Base64URL cursor.
	 *
	 * @param BatchExportCursor $cursor Internal cursor.
	 */
	public function encode( BatchExportCursor $cursor ): string {
		return rtrim(
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Benign versioned REST cursor encoding.
			strtr( base64_encode( self::PREFIX . $cursor->export_version ), '+/', '-_' ),
			'='
		);
	}

	/**
	 * Decode one strict versioned Base64URL cursor.
	 *
	 * @param string $encoded External cursor.
	 * @throws InvalidArgumentException When the cursor is malformed or unknown.
	 */
	public function decode( string $encoded ): BatchExportCursor {
		if ( '' === $encoded || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/D', $encoded ) ) {
			throw new InvalidArgumentException( 'Batch export cursor is invalid.' );
		}

		$padding = ( 4 - ( strlen( $encoded ) % 4 ) ) % 4;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes a validated non-secret pagination cursor.
		$decoded = base64_decode(
			strtr( $encoded, '-_', '+/' ) . str_repeat( '=', $padding ),
			true
		);

		if ( ! is_string( $decoded ) || ! str_starts_with( $decoded, self::PREFIX ) ) {
			throw new InvalidArgumentException( 'Batch export cursor is invalid.' );
		}

		$value = substr( $decoded, strlen( self::PREFIX ) );

		if ( 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
			throw new InvalidArgumentException( 'Batch export cursor is invalid.' );
		}

		$cursor = new BatchExportCursor( (int) $value );

		if ( $this->encode( $cursor ) !== $encoded ) {
			throw new InvalidArgumentException( 'Batch export cursor is invalid.' );
		}

		return $cursor;
	}
}
