<?php
/**
 * Encrypted private-media object reference.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Value;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * Prevents private object references from being exposed as ordinary strings.
 */
final readonly class PrivateMediaReferenceCiphertext {
	/**
	 * Create an encrypted private object reference.
	 *
	 * @param string $value Opaque encrypted bytes.
	 */
	private function __construct( public string $value ) {
		RecordValidator::opaque_bytes( $this->value, 65535, 'object_reference_ciphertext' );
	}

	/**
	 * Wrap an encrypted private object reference.
	 *
	 * @param string $value Opaque encrypted bytes.
	 */
	public static function from_encrypted_bytes( string $value ): self {
		return new self( $value );
	}

	/**
	 * Reconstitute a stored encrypted reference.
	 *
	 * @param string $value Stored opaque bytes.
	 */
	public static function from_storage( string $value ): self {
		return new self( $value );
	}
}
