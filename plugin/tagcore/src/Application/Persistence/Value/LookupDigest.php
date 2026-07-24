<?php
/**
 * Keyed lookup digest persistence value.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Value;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * Represents a canonical keyed lookup digest, never its source value.
 */
final readonly class LookupDigest {
	/**
	 * Create a keyed lookup digest.
	 *
	 * @param string $value Canonical lowercase hexadecimal digest.
	 */
	private function __construct( public string $value ) {
		RecordValidator::digest( $this->value, 'lookup_digest' );
	}

	/**
	 * Wrap a digest returned by an approved HMAC adapter.
	 *
	 * @param string $value Canonical lowercase hexadecimal digest.
	 */
	public static function from_digest( string $value ): self {
		return new self( $value );
	}

	/**
	 * Reconstitute a stored keyed lookup digest.
	 *
	 * @param string $value Stored canonical digest.
	 */
	public static function from_storage( string $value ): self {
		return new self( $value );
	}
}
