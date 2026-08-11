<?php
/**
 * Owner Account OTP protection port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;

interface AccountOtpProtector {
	/**
	 * Create an Account-specific email lookup digest.
	 *
	 * @param EmailAddress $email Canonical email.
	 */
	public function email_lookup( EmailAddress $email ): LookupDigest;

	/**
	 * Create an Account-specific direct-peer IP digest.
	 *
	 * @param string $ip_address Canonical direct-peer IP.
	 */
	public function ip_lookup( string $ip_address ): LookupDigest;

	/**
	 * Encrypt one email under its opaque Account subject.
	 *
	 * @param EmailAddress $email Canonical email.
	 * @param LookupDigest $subject Opaque Account subject.
	 */
	public function encrypt_email( EmailAddress $email, LookupDigest $subject ): EmailCiphertext;

	/**
	 * Decrypt one email under its opaque Account subject.
	 *
	 * @param EmailCiphertext $ciphertext Authenticated envelope.
	 * @param LookupDigest    $subject Opaque Account subject.
	 */
	public function decrypt_email( EmailCiphertext $ciphertext, LookupDigest $subject ): EmailAddress;

	/** Create a non-issuable placeholder code hash. */
	public function placeholder_hash(): OtpHash;

	/**
	 * Hash one six-digit code under the Account domain.
	 *
	 * @param string $code Exact six-digit code.
	 */
	public function hash_code( string $code ): OtpHash;

	/**
	 * Compare one six-digit code in constant time.
	 *
	 * @param string  $code Exact six-digit code.
	 * @param OtpHash $hash Stored adaptive hash.
	 */
	public function verify_code( string $code, OtpHash $hash ): bool;
}
