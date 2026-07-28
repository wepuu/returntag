<?php
/**
 * Batch Tag inventory REST cursor codec.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Batch\BatchTagInventoryCursor;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Keeps internal keyset fields out of the public REST shape.
 *
 * The cursor is opaque and validated, but not a secret or authorization token.
 */
final class BatchTagInventoryCursorCodec {
	private const PREFIX = 'rt206:';

	/**
	 * Encode one versioned Base64URL cursor.
	 *
	 * @param BatchTagInventoryCursor $cursor Internal cursor.
	 */
	public function encode( BatchTagInventoryCursor $cursor ): string {
		return rtrim(
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Benign versioned REST cursor encoding, not executable code or a secret.
			strtr( base64_encode( self::PREFIX . $cursor->tag_id->value ), '+/', '-_' ),
			'='
		);
	}

	/**
	 * Decode one strict versioned Base64URL cursor.
	 *
	 * @param string $encoded External cursor.
	 * @throws InvalidArgumentException When the cursor is malformed or unknown.
	 */
	public function decode( string $encoded ): BatchTagInventoryCursor {
		if ( '' === $encoded || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/D', $encoded ) ) {
			throw new InvalidArgumentException( 'Batch Tag inventory cursor is invalid.' );
		}

		$padding = ( 4 - ( strlen( $encoded ) % 4 ) ) % 4;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes a strictly validated non-secret pagination cursor.
		$decoded = base64_decode(
			strtr( $encoded, '-_', '+/' ) . str_repeat( '=', $padding ),
			true
		);

		if ( ! is_string( $decoded ) || ! str_starts_with( $decoded, self::PREFIX ) ) {
			throw new InvalidArgumentException( 'Batch Tag inventory cursor is invalid.' );
		}

		$tag_id = substr( $decoded, strlen( self::PREFIX ) );
		$cursor = new BatchTagInventoryCursor( TagId::from_canonical( $tag_id ) );

		if ( $this->encode( $cursor ) !== $encoded ) {
			throw new InvalidArgumentException( 'Batch Tag inventory cursor is invalid.' );
		}

		return $cursor;
	}
}
