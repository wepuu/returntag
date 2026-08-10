<?php
/**
 * Sodium Finder email verification protection.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Security;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\FinderReport\FinderEmailProtector;
use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use RuntimeException;

/** XChaCha20-Poly1305 and domain-separated keyed hashes for Finder email OTP. */
final readonly class SodiumFinderEmailProtector implements FinderEmailProtector {
	private const PREFIX = 'RTFEM1:v1:';

	/**
	 * Create the protection adapter.
	 *
	 * @param FinderEmailVerificationSecrets $secrets Independent keys.
	 * @throws RuntimeException When Sodium is unavailable.
	 */
	public function __construct( private FinderEmailVerificationSecrets $secrets ) {
		if ( ! function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' ) ) {
			throw new RuntimeException( 'Finder email verification encryption is unavailable.' );
		}
	}

	/**
	 * Create a keyed email lookup.
	 *
	 * @param EmailAddress $email Canonical email.
	 */
	public function email_lookup( EmailAddress $email ): LookupDigest {
		return LookupDigest::from_digest( hash_hmac( 'sha256', 'finder-email:v1:' . $email->value, $this->secrets->lookup_key ) );
	}

	/**
	 * Create a keyed peer-IP lookup.
	 *
	 * @param string $ip_address Canonical peer IP.
	 * @throws InvalidArgumentException When the IP is invalid.
	 */
	public function ip_lookup( string $ip_address ): LookupDigest {
		$packed = inet_pton( $ip_address );
		if ( false === $packed ) {
			throw new InvalidArgumentException( 'IP address is invalid.' );
		}
		return LookupDigest::from_digest( hash_hmac( 'sha256', "finder-email-ip:v1:\0" . $packed, $this->secrets->lookup_key ) );
	}

	/**
	 * Encrypt one report-bound Finder email.
	 *
	 * @param EmailAddress $email Canonical email.
	 * @param int          $finder_report_id Internal report identifier.
	 */
	public function encrypt_email( EmailAddress $email, int $finder_report_id ): EmailCiphertext {
		$this->assert_report( $finder_report_id );
		$nonce     = random_bytes( SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
		$encrypted = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( $email->value, $this->aad( $finder_report_id ), $nonce, $this->secrets->email_key );
		return EmailCiphertext::from_encrypted_bytes( self::PREFIX . sodium_bin2base64( $nonce . $encrypted, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING ) );
	}

	/**
	 * Decrypt one report-bound Finder email.
	 *
	 * @param EmailCiphertext $ciphertext Opaque envelope.
	 * @param int             $finder_report_id Internal report identifier.
	 * @throws RuntimeException When authenticated decryption fails.
	 */
	public function decrypt_email( EmailCiphertext $ciphertext, int $finder_report_id ): EmailAddress {
		$this->assert_report( $finder_report_id );
		if ( ! str_starts_with( $ciphertext->value, self::PREFIX ) ) {
			throw new RuntimeException( 'Finder email envelope is invalid.' );
		}
		try {
			$payload = sodium_base642bin( substr( $ciphertext->value, strlen( self::PREFIX ) ), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING, '' );
		} catch ( \SodiumException ) {
			throw new RuntimeException( 'Finder email envelope is invalid.' );
		}
		$length = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
		if ( strlen( $payload ) <= $length ) {
			throw new RuntimeException( 'Finder email envelope is invalid.' );
		}
		$value = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt( substr( $payload, $length ), $this->aad( $finder_report_id ), substr( $payload, 0, $length ), $this->secrets->email_key );
		if ( false === $value ) {
			throw new RuntimeException( 'Finder email envelope is invalid.' );
		}
		return new EmailAddress( $value );
	}

	/** Create an unissuable placeholder hash. */
	public function placeholder_hash(): OtpHash {
		return $this->password_hash( hash_hmac( 'sha256', "finder-email-unissued:v1:\0" . random_bytes( 32 ), $this->secrets->otp_pepper ) );
	}

	/**
	 * Hash one issued OTP.
	 *
	 * @param string $code Six-digit OTP.
	 */
	public function hash_code( string $code ): OtpHash {
		return $this->password_hash( $this->digest( $code ) );
	}

	/**
	 * Compare one OTP with a stored hash.
	 *
	 * @param string  $code Six-digit OTP.
	 * @param OtpHash $hash Stored password hash.
	 */
	public function verify_code( string $code, OtpHash $hash ): bool {
		return password_verify( $this->digest( $code ), $hash->value );
	}

	/**
	 * Build stable authenticated associated data.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 */
	private function aad( int $finder_report_id ): string {
		return 'finder_email_otp|finder_report|' . $finder_report_id . '|v1';
	}

	/**
	 * Build one domain-separated OTP digest.
	 *
	 * @param string $code Six-digit OTP.
	 * @throws InvalidArgumentException When the code shape is invalid.
	 */
	private function digest( string $code ): string {
		if ( 1 !== preg_match( '/^[0-9]{6}$/D', $code ) ) {
			throw new InvalidArgumentException( 'OTP code is invalid.' );
		}
		return hash_hmac( 'sha256', 'finder-email-otp:v1:' . $code, $this->secrets->otp_pepper );
	}

	/**
	 * Wrap one digest in an adaptive password hash.
	 *
	 * @param string $value Keyed digest.
	 */
	private function password_hash( string $value ): OtpHash {
		return OtpHash::from_password_hash( password_hash( $value, PASSWORD_DEFAULT ) );
	}

	/**
	 * Reject invalid internal identifiers.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 * @throws InvalidArgumentException When the identifier is invalid.
	 */
	private function assert_report( int $finder_report_id ): void {
		if ( $finder_report_id < 1 ) {
			throw new InvalidArgumentException( 'Finder Report identifier is invalid.' );
		}
	}
}
