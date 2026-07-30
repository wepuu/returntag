<?php
/**
 * Sodium and keyed-hash activation OTP protection.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Security;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Auth\ActivationOtpProtector;
use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Tag\TagId;
use RuntimeException;

/**
 * Uses XChaCha20-Poly1305 plus distinct HMAC/password-hash domains.
 */
final readonly class SodiumActivationOtpProtector implements ActivationOtpProtector {
	private const ENVELOPE_PREFIX = 'RTOTP1:v1:';

	/**
	 * Create the protection adapter.
	 *
	 * @param ActivationOtpSecrets $secrets Independent external keys.
	 * @throws RuntimeException When Sodium is unavailable.
	 */
	public function __construct( private ActivationOtpSecrets $secrets ) {
		if ( ! function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' ) ) {
			throw new RuntimeException( 'Activation OTP encryption is unavailable.' );
		}
	}

	/**
	 * Create a keyed email digest.
	 *
	 * @param EmailAddress $email Canonical email.
	 */
	public function email_lookup( EmailAddress $email ): LookupDigest {
		return LookupDigest::from_digest(
			hash_hmac( 'sha256', 'activation-email:v1:' . $email->value, $this->secrets->lookup_key )
		);
	}

	/**
	 * Create a keyed binary-IP digest.
	 *
	 * @param string $ip_address Canonical IP address.
	 * @throws InvalidArgumentException When the IP address is invalid.
	 */
	public function ip_lookup( string $ip_address ): LookupDigest {
		$packed = inet_pton( $ip_address );

		if ( false === $packed ) {
			throw new InvalidArgumentException( 'IP address is invalid.' );
		}

		return LookupDigest::from_digest(
			hash_hmac( 'sha256', "activation-ip:v1:\0" . $packed, $this->secrets->lookup_key )
		);
	}

	/**
	 * Encrypt one Tag-bound email envelope.
	 *
	 * @param EmailAddress $email Canonical email.
	 * @param TagId        $tag_id Public Tag.
	 */
	public function encrypt_email( EmailAddress $email, TagId $tag_id ): EmailCiphertext {
		$nonce      = random_bytes( SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
		$ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
			$email->value,
			$this->associated_data( $tag_id ),
			$nonce,
			$this->secrets->email_key
		);

		return EmailCiphertext::from_encrypted_bytes(
			self::ENVELOPE_PREFIX . sodium_bin2base64(
				$nonce . $ciphertext,
				SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING
			)
		);
	}

	/**
	 * Decrypt one Tag-bound email envelope.
	 *
	 * @param EmailCiphertext $ciphertext Authenticated envelope.
	 * @param TagId           $tag_id Public Tag.
	 * @throws RuntimeException When authentication fails.
	 */
	public function decrypt_email( EmailCiphertext $ciphertext, TagId $tag_id ): EmailAddress {
		if ( ! str_starts_with( $ciphertext->value, self::ENVELOPE_PREFIX ) ) {
			throw new RuntimeException( 'Encrypted email envelope is invalid.' );
		}

		try {
			$payload = sodium_base642bin(
				substr( $ciphertext->value, strlen( self::ENVELOPE_PREFIX ) ),
				SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
				''
			);
		} catch ( \SodiumException ) {
			throw new RuntimeException( 'Encrypted email envelope is invalid.' );
		}

		$nonce_length = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

		if ( strlen( $payload ) <= $nonce_length ) {
			throw new RuntimeException( 'Encrypted email envelope is invalid.' );
		}

		$plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
			substr( $payload, $nonce_length ),
			$this->associated_data( $tag_id ),
			substr( $payload, 0, $nonce_length ),
			$this->secrets->email_key
		);

		if ( false === $plaintext ) {
			throw new RuntimeException( 'Encrypted email envelope is invalid.' );
		}

		return new EmailAddress( $plaintext );
	}

	/**
	 * Create an impossible-to-issue placeholder domain hash.
	 */
	public function placeholder_hash(): OtpHash {
		return $this->password_hash(
			hash_hmac(
				'sha256',
				"activation-otp-unissued:v1:\0" . random_bytes( 32 ),
				$this->secrets->otp_pepper
			)
		);
	}

	/**
	 * Hash one issued six-digit code.
	 *
	 * @param string $code Six-digit code.
	 * @throws InvalidArgumentException When the code shape is invalid.
	 */
	public function hash_code( string $code ): OtpHash {
		if ( 1 !== preg_match( '/^\d{6}$/D', $code ) ) {
			throw new InvalidArgumentException( 'OTP code is invalid.' );
		}

		return $this->password_hash(
			hash_hmac( 'sha256', 'activation-otp-issued:v1:' . $code, $this->secrets->otp_pepper )
		);
	}

	/**
	 * Build the stable authenticated context.
	 *
	 * @param TagId $tag_id Public Tag.
	 */
	private function associated_data( TagId $tag_id ): string {
		return 'activation_otp|tag|' . $tag_id->value . '|v1';
	}

	/**
	 * Wrap one keyed digest in PHP's adaptive password hash.
	 *
	 * @param string $value Keyed digest.
	 * @throws RuntimeException When password hashing fails.
	 */
	private function password_hash( string $value ): OtpHash {
		$hash = password_hash( $value, PASSWORD_DEFAULT );

		return OtpHash::from_password_hash( $hash );
	}
}
