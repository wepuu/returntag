<?php
/**
 * Sodium protection for Owner Account OTP data.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Security;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Auth\AccountOtpProtector;
use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use RuntimeException;

/**
 * Reuses externally managed OTP keys with Account-specific cryptographic domains.
 */
final readonly class SodiumAccountOtpProtector implements AccountOtpProtector {
	private const ENVELOPE_PREFIX = 'RTACCT1:v1:';

	/**
	 * Create the Account protection adapter.
	 *
	 * @param ActivationOtpSecrets $secrets Independent external keys.
	 * @throws RuntimeException When Sodium is unavailable.
	 */
	public function __construct( private ActivationOtpSecrets $secrets ) {
		if ( ! function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' ) ) {
			throw new RuntimeException( 'Account OTP encryption is unavailable.' );
		}
	}

	/**
	 * Create a keyed Account email digest.
	 *
	 * @param EmailAddress $email Canonical email.
	 */
	public function email_lookup( EmailAddress $email ): LookupDigest {
		return LookupDigest::from_digest(
			hash_hmac( 'sha256', 'account-email:v1:' . $email->value, $this->secrets->lookup_key )
		);
	}

	/**
	 * Create a keyed Account direct-peer IP digest.
	 *
	 * @param string $ip_address Canonical direct-peer IP.
	 * @throws InvalidArgumentException When the address is invalid.
	 */
	public function ip_lookup( string $ip_address ): LookupDigest {
		$packed = inet_pton( $ip_address );

		if ( false === $packed ) {
			throw new InvalidArgumentException( 'IP address is invalid.' );
		}

		return LookupDigest::from_digest(
			hash_hmac( 'sha256', "account-ip:v1:\0" . $packed, $this->secrets->lookup_key )
		);
	}

	/**
	 * Encrypt one email with Account-bound associated data.
	 *
	 * @param EmailAddress $email Canonical email.
	 * @param LookupDigest $subject Opaque Account subject.
	 */
	public function encrypt_email( EmailAddress $email, LookupDigest $subject ): EmailCiphertext {
		$nonce      = random_bytes( SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
		$ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
			$email->value,
			$this->associated_data( $subject ),
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
	 * Decrypt one Account-bound email envelope.
	 *
	 * @param EmailCiphertext $ciphertext Authenticated envelope.
	 * @param LookupDigest    $subject Opaque Account subject.
	 * @throws RuntimeException When envelope authentication fails.
	 */
	public function decrypt_email( EmailCiphertext $ciphertext, LookupDigest $subject ): EmailAddress {
		if ( ! str_starts_with( $ciphertext->value, self::ENVELOPE_PREFIX ) ) {
			throw new RuntimeException( 'Encrypted Account email envelope is invalid.' );
		}

		try {
			$payload = sodium_base642bin(
				substr( $ciphertext->value, strlen( self::ENVELOPE_PREFIX ) ),
				SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
				''
			);
		} catch ( \SodiumException ) {
			throw new RuntimeException( 'Encrypted Account email envelope is invalid.' );
		}

		$nonce_length = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

		if ( strlen( $payload ) <= $nonce_length ) {
			throw new RuntimeException( 'Encrypted Account email envelope is invalid.' );
		}

		$plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
			substr( $payload, $nonce_length ),
			$this->associated_data( $subject ),
			substr( $payload, 0, $nonce_length ),
			$this->secrets->email_key
		);

		if ( false === $plaintext ) {
			throw new RuntimeException( 'Encrypted Account email envelope is invalid.' );
		}

		return new EmailAddress( $plaintext );
	}

	/** Create one impossible-to-issue placeholder hash. */
	public function placeholder_hash(): OtpHash {
		return $this->password_hash(
			hash_hmac( 'sha256', "account-otp-unissued:v1:\0" . random_bytes( 32 ), $this->secrets->otp_pepper )
		);
	}

	/**
	 * Hash one issued Account code.
	 *
	 * @param string $code Exact six-digit code.
	 */
	public function hash_code( string $code ): OtpHash {
		return $this->password_hash( $this->issued_digest( $code ) );
	}

	/**
	 * Compare one Account code with an issued hash.
	 *
	 * @param string  $code Exact six-digit code.
	 * @param OtpHash $hash Stored adaptive hash.
	 */
	public function verify_code( string $code, OtpHash $hash ): bool {
		return password_verify( $this->issued_digest( $code ), $hash->value );
	}

	/**
	 * Build stable Account-scoped associated data.
	 *
	 * @param LookupDigest $subject Opaque Account subject.
	 */
	private function associated_data( LookupDigest $subject ): string {
		return 'account_otp|account|' . $subject->value . '|v1';
	}

	/**
	 * Create one domain-separated issued-code digest.
	 *
	 * @param string $code Exact six-digit code.
	 * @throws InvalidArgumentException When the code shape is invalid.
	 */
	private function issued_digest( string $code ): string {
		if ( 1 !== preg_match( '/^[0-9]{6}$/D', $code ) ) {
			throw new InvalidArgumentException( 'Account OTP code is invalid.' );
		}

		return hash_hmac( 'sha256', 'account-otp-issued:v1:' . $code, $this->secrets->otp_pepper );
	}

	/**
	 * Wrap one keyed digest in an adaptive password hash.
	 *
	 * @param string $value Keyed digest.
	 */
	private function password_hash( string $value ): OtpHash {
		$hash = password_hash( $value, PASSWORD_DEFAULT );

		return OtpHash::from_password_hash( $hash );
	}
}
