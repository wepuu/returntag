<?php
/**
 * Encrypted message persistence value.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Value;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * Prevents encrypted message envelopes from being confused with plaintext.
 */
final readonly class MessageCiphertext {
	/**
	 * Create an encrypted message value.
	 *
	 * @param string $value Opaque encrypted bytes.
	 */
	private function __construct( public string $value ) {
		RecordValidator::opaque_bytes( $this->value, 65535, 'body_ciphertext' );
	}

	/**
	 * Wrap bytes returned by an approved encryption adapter.
	 *
	 * @param string $value Opaque encrypted bytes.
	 */
	public static function from_encrypted_bytes( string $value ): self {
		return new self( $value );
	}

	/**
	 * Reconstitute a stored encrypted message envelope.
	 *
	 * @param string $value Stored opaque bytes.
	 */
	public static function from_storage( string $value ): self {
		return new self( $value );
	}
}
