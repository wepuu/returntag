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
	 * Create the administrative search normalizer.
	 *
	 * @param TagIdInputNormalizer $tag_ids Shared Tag ID input boundary.
	 */
	public function __construct( private readonly TagIdInputNormalizer $tag_ids ) {
	}

	/**
	 * Normalize spaces and hyphens before strict canonical Tag ID validation.
	 *
	 * @param string $value Operator input.
	 * @throws InvalidArgumentException When normalization does not produce a canonical Tag ID.
	 */
	public function tag_id( string $value ): TagId {
		return $this->tag_ids->normalize( $value );
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
