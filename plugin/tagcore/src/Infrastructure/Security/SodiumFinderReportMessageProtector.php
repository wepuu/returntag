<?php
/**
 * Sodium Finder Report message protector.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Security;

use ReturnTag\TagCore\Application\FinderReport\FinderReportMessageProtector;
use ReturnTag\TagCore\Application\Persistence\Value\FinderReportMessageCiphertext;
use ReturnTag\TagCore\Domain\Tag\TagId;
use RuntimeException;

/** Encrypts messages with a versioned, Tag-bound authenticated envelope. */
final readonly class SodiumFinderReportMessageProtector implements FinderReportMessageProtector {
	private const VERSION = "\x01";

	/**
	 * Create the protector.
	 *
	 * @param FinderReportMessageSecrets $secrets Dedicated key material.
	 */
	public function __construct( private FinderReportMessageSecrets $secrets ) {
	}

	/**
	 * Encrypt one Tag-bound message.
	 *
	 * @param string $message Plaintext message.
	 * @param TagId  $tag_id Associated Tag.
	 */
	public function encrypt( string $message, TagId $tag_id ): FinderReportMessageCiphertext {
		$nonce      = random_bytes( SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
		$ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
			$message,
			$this->associated_data( $tag_id ),
			$nonce,
			$this->secrets->key
		);

		return FinderReportMessageCiphertext::from_encrypted_bytes( self::VERSION . $nonce . $ciphertext );
	}

	/**
	 * Decrypt one Tag-bound message.
	 *
	 * @param FinderReportMessageCiphertext $ciphertext Stored ciphertext.
	 * @param TagId                         $tag_id Associated Tag.
	 * @throws RuntimeException When authentication fails.
	 */
	public function decrypt( FinderReportMessageCiphertext $ciphertext, TagId $tag_id ): string {
		$minimum = 1 + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;

		if ( strlen( $ciphertext->value ) < $minimum || ! str_starts_with( $ciphertext->value, self::VERSION ) ) {
			throw new RuntimeException( 'Finder Report message cannot be decrypted.' );
		}

		$nonce = substr( $ciphertext->value, 1, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
		$body  = substr( $ciphertext->value, 1 + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
		$value = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
			$body,
			$this->associated_data( $tag_id ),
			$nonce,
			$this->secrets->key
		);

		if ( false === $value ) {
			throw new RuntimeException( 'Finder Report message cannot be decrypted.' );
		}

		return $value;
	}

	/**
	 * Build purpose- and Tag-bound associated data.
	 *
	 * @param TagId $tag_id Associated Tag.
	 */
	private function associated_data( TagId $tag_id ): string {
		return 'returntag:finder-report-message:' . FinderReportMessageSecrets::KEY_ID . "\0" . $tag_id->value;
	}
}
