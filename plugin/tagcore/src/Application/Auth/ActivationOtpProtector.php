<?php
/**
 * Activation OTP sensitive-data protection port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Keeps encryption and keyed hashing outside Application.
 */
interface ActivationOtpProtector {
	/**
	 * Create an equality-safe email digest.
	 *
	 * @param EmailAddress $email Canonical email.
	 */
	public function email_lookup( EmailAddress $email ): LookupDigest;

	/**
	 * Create an equality-safe IP digest.
	 *
	 * @param string $ip_address Canonical IP address.
	 */
	public function ip_lookup( string $ip_address ): LookupDigest;

	/**
	 * Encrypt one email with Tag-bound associated data.
	 *
	 * @param EmailAddress $email Canonical email.
	 * @param TagId        $tag_id Public Tag.
	 */
	public function encrypt_email( EmailAddress $email, TagId $tag_id ): EmailCiphertext;

	/**
	 * Decrypt one Tag-bound email envelope.
	 *
	 * @param EmailCiphertext $ciphertext Authenticated envelope.
	 * @param TagId           $tag_id Public Tag.
	 */
	public function decrypt_email( EmailCiphertext $ciphertext, TagId $tag_id ): EmailAddress;

	/**
	 * Create a domain-separated unissued placeholder hash.
	 */
	public function placeholder_hash(): OtpHash;

	/**
	 * Hash one issued six-digit OTP through the dedicated pepper.
	 *
	 * @param string $code Six-digit code.
	 */
	public function hash_code( string $code ): OtpHash;
}
