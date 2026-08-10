<?php
/**
 * Finder email sensitive-data protection port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/** Protects Finder identity and OTP values in a report-bound domain. */
interface FinderEmailProtector {
	/**
	 * Derive a privacy-safe email lookup digest.
	 *
	 * @param EmailAddress $email Canonical email.
	 */
	public function email_lookup( EmailAddress $email ): LookupDigest;

	/**
	 * Derive a privacy-safe peer IP lookup digest.
	 *
	 * @param string $ip_address Canonical peer IP.
	 */
	public function ip_lookup( string $ip_address ): LookupDigest;
	/**
	 * Encrypt one report-bound email address.
	 *
	 * @param EmailAddress $email Canonical email.
	 * @param int          $finder_report_id Internal report identifier.
	 */
	public function encrypt_email( EmailAddress $email, int $finder_report_id ): EmailCiphertext;
	/**
	 * Decrypt one report-bound email address.
	 *
	 * @param EmailCiphertext $ciphertext Opaque encrypted email.
	 * @param int             $finder_report_id Internal report identifier.
	 */
	public function decrypt_email( EmailCiphertext $ciphertext, int $finder_report_id ): EmailAddress;
	/** Create an unissuable placeholder hash. */
	public function placeholder_hash(): OtpHash;
	/**
	 * Hash one six-digit verification code.
	 *
	 * @param string $code Six-digit code.
	 */
	public function hash_code( string $code ): OtpHash;
	/**
	 * Compare one six-digit code with a stored hash.
	 *
	 * @param string  $code Six-digit code.
	 * @param OtpHash $hash Stored hash.
	 */
	public function verify_code( string $code, OtpHash $hash ): bool;
}
