<?php
/**
 * Private-media SHA-256 digest value.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Value;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * Canonical digest used only for private-media integrity.
 */
final readonly class MediaDigest {
	/**
	 * Create a private-media integrity digest.
	 *
	 * @param string $value Canonical SHA-256 digest.
	 */
	private function __construct( public string $value ) {
		RecordValidator::digest( $this->value, 'media_digest' );
	}

	/**
	 * Wrap an integrity digest.
	 *
	 * @param string $value Canonical SHA-256 digest.
	 */
	public static function from_digest( string $value ): self {
		return new self( $value );
	}

	/**
	 * Reconstitute a stored digest.
	 *
	 * @param string $value Stored canonical digest.
	 */
	public static function from_storage( string $value ): self {
		return new self( $value );
	}
}
