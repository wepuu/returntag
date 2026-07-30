<?php
/**
 * Public Tag ID input normalization.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use InvalidArgumentException;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Converts bounded human input into the canonical public Tag ID value.
 */
final class TagIdInputNormalizer {
	/**
	 * Maximum raw input size accepted at a public boundary.
	 */
	public const MAX_INPUT_BYTES = 64;

	/**
	 * Remove formatting characters and validate the canonical Tag ID.
	 *
	 * @param string $value Untrusted human or route input.
	 * @throws InvalidArgumentException When normalization cannot produce a canonical Tag ID.
	 */
	public function normalize( string $value ): TagId {
		if ( self::MAX_INPUT_BYTES < strlen( $value ) ) {
			throw new InvalidArgumentException( 'Tag ID input is invalid.' );
		}

		$normalized = preg_replace( '/[\s-]+/u', '', strtoupper( $value ) );

		if ( ! is_string( $normalized ) ) {
			throw new InvalidArgumentException( 'Tag ID input is invalid.' );
		}

		return TagId::from_canonical( $normalized );
	}
}
