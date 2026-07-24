<?php
/**
 * Access Token digest persistence value.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Value;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * Represents a canonical digest rather than a plaintext Access Token.
 */
final readonly class AccessTokenDigest {
	/**
	 * Create an Access Token digest.
	 *
	 * @param string $value Canonical lowercase hexadecimal digest.
	 */
	private function __construct( public string $value ) {
		RecordValidator::digest( $this->value, 'token_hash' );
	}

	/**
	 * Wrap a digest returned by an approved Token hashing adapter.
	 *
	 * @param string $value Canonical lowercase hexadecimal digest.
	 */
	public static function from_digest( string $value ): self {
		return new self( $value );
	}

	/**
	 * Reconstitute a stored Access Token digest.
	 *
	 * @param string $value Stored canonical digest.
	 */
	public static function from_storage( string $value ): self {
		return new self( $value );
	}
}
