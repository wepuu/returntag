<?php
/**
 * Activation OTP verification persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Owns the row lock, attempt counter, and one-time terminal transition.
 */
interface ActivationOtpVerificationStore {
	/**
	 * Determine whether the latest matching challenge may enter verification.
	 *
	 * This is an allocation gate only. verify_latest() remains the authoritative
	 * locked state transition and must repeat every eligibility check.
	 *
	 * @param TagId             $tag_id Public Tag.
	 * @param LookupDigest      $email_lookup Keyed email digest.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param int               $maximum_attempts Hard attempt ceiling.
	 */
	public function has_verifiable_latest(
		TagId $tag_id,
		LookupDigest $email_lookup,
		DateTimeImmutable $now,
		int $maximum_attempts
	): bool;

	/**
	 * Verify the latest matching challenge atomically.
	 *
	 * @param TagId                   $tag_id Public Tag.
	 * @param LookupDigest            $email_lookup Keyed email digest.
	 * @param DateTimeImmutable       $now Current UTC time.
	 * @param int                     $maximum_attempts Hard attempt ceiling.
	 * @param callable(OtpHash): bool $matches Constant-time code comparison.
	 */
	public function verify_latest(
		TagId $tag_id,
		LookupDigest $email_lookup,
		DateTimeImmutable $now,
		int $maximum_attempts,
		callable $matches
	): ActivationOtpVerificationResult;
}
