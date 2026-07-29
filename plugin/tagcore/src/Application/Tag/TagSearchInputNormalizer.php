<?php
/**
 * Administrative Tag search input normalization.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Normalizes only the two approved exact-match search anchors.
 */
final class TagSearchInputNormalizer {
	/**
	 * Normalize spaces and hyphens before strict canonical Tag ID validation.
	 *
	 * @param string $value Operator input.
	 * @throws InvalidArgumentException When normalization does not produce a canonical Tag ID.
	 */
	public function tag_id( string $value ): TagId {
		$normalized = preg_replace( '/[\s-]+/u', '', strtoupper( $value ) );

		if ( ! is_string( $normalized ) ) {
			throw new InvalidArgumentException( 'Tag ID search value is invalid.' );
		}

		return TagId::from_canonical( $normalized );
	}

	/**
	 * Normalize surrounding whitespace while preserving exact Batch Code case.
	 *
	 * @param string $value Operator input.
	 */
	public function batch_code( string $value ): string {
		return RecordValidator::ascii( trim( $value ), 191, 'batch_code' );
	}
}
