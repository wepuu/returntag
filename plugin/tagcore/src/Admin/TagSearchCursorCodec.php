<?php
/**
 * Tag search REST cursor codec.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Tag\TagSearchCriteria;
use ReturnTag\TagCore\Application\Tag\TagSearchCursor;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Produces versioned cursors bound to normalized filters.
 */
final class TagSearchCursorCodec {
	private const PREFIX = 'rt209:';

	/**
	 * Encode a cursor bound to normalized search filters.
	 *
	 * @param TagSearchCriteria $criteria Search filters.
	 * @param TagSearchCursor   $cursor Internal cursor.
	 */
	public function encode( TagSearchCriteria $criteria, TagSearchCursor $cursor ): string {
		$payload = self::PREFIX . $criteria->fingerprint() . ':' . $cursor->tag_id->value;

		return rtrim(
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Benign opaque pagination cursor.
			strtr( base64_encode( $payload ), '+/', '-_' ),
			'='
		);
	}

	/**
	 * Decode and verify a filter-bound cursor.
	 *
	 * @param TagSearchCriteria $criteria Current search filters.
	 * @param string            $encoded External cursor.
	 * @throws InvalidArgumentException When the cursor is malformed or bound to different filters.
	 */
	public function decode( TagSearchCriteria $criteria, string $encoded ): TagSearchCursor {
		if ( '' === $encoded || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/D', $encoded ) ) {
			throw new InvalidArgumentException( 'Tag search cursor is invalid.' );
		}

		$padding = ( 4 - ( strlen( $encoded ) % 4 ) ) % 4;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes a strictly validated non-secret pagination cursor.
		$decoded = base64_decode( strtr( $encoded, '-_', '+/' ) . str_repeat( '=', $padding ), true );
		$prefix  = self::PREFIX . $criteria->fingerprint() . ':';

		if ( ! is_string( $decoded ) || ! str_starts_with( $decoded, $prefix ) ) {
			throw new InvalidArgumentException( 'Tag search cursor is invalid.' );
		}

		$cursor = new TagSearchCursor( TagId::from_canonical( substr( $decoded, strlen( $prefix ) ) ) );

		if ( $this->encode( $criteria, $cursor ) !== $encoded ) {
			throw new InvalidArgumentException( 'Tag search cursor is invalid.' );
		}

		return $cursor;
	}
}
