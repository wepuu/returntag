<?php
/**
 * Encrypted email persistence value.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Value;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * Prevents encrypted email envelopes from being confused with plaintext.
 */
final readonly class EmailCiphertext {
	/**
	 * Create an encrypted email value.
	 *
	 * @param string $value Opaque encrypted bytes.
	 */
	private function __construct( public string $value ) {
		RecordValidator::opaque_bytes( $this->value, 4096, 'email_ciphertext' );
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
	 * Reconstitute a stored encrypted email envelope.
	 *
	 * @param string $value Stored opaque bytes.
	 */
	public static function from_storage( string $value ): self {
		return new self( $value );
	}
}
