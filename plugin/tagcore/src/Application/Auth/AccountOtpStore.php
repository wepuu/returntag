<?php
/**
 * Owner Account OTP persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Record\AuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewAuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;

interface AccountOtpStore {
	/**
	 * Count recent Account challenges for one keyed email.
	 *
	 * @param LookupDigest      $email_lookup Keyed email digest.
	 * @param DateTimeImmutable $since Inclusive UTC boundary.
	 */
	public function count_recent_for_email( LookupDigest $email_lookup, DateTimeImmutable $since ): int;

	/**
	 * Consume prior open matches and insert one unissued challenge atomically.
	 *
	 * @param NewAuthChallengeRecord $challenge Unissued Account challenge.
	 */
	public function create_replacing( NewAuthChallengeRecord $challenge ): AuthChallengeRecord;

	/**
	 * Find one challenge by internal identifier.
	 *
	 * @param int $challenge_id Positive challenge identifier.
	 */
	public function find_by_id( int $challenge_id ): ?AuthChallengeRecord;

	/**
	 * Claim the latest unissued challenge for one at-most-once dispatch.
	 *
	 * @param int               $challenge_id Positive challenge identifier.
	 * @param OtpHash           $code_hash Issued code hash.
	 * @param DateTimeImmutable $expires_at Issued code expiry.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function claim_for_dispatch(
		int $challenge_id,
		OtpHash $code_hash,
		DateTimeImmutable $expires_at,
		DateTimeImmutable $now
	): ?AuthChallengeRecord;

	/**
	 * Determine whether the latest challenge may allocate email verification scope.
	 *
	 * @param LookupDigest      $email_lookup Keyed email digest.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param int               $maximum_attempts Hard attempt ceiling.
	 */
	public function has_verifiable_latest(
		LookupDigest $email_lookup,
		DateTimeImmutable $now,
		int $maximum_attempts
	): bool;

	/**
	 * Verify the latest matching challenge atomically.
	 *
	 * @param LookupDigest            $email_lookup Keyed email digest.
	 * @param DateTimeImmutable       $now Current UTC time.
	 * @param int                     $maximum_attempts Hard attempt ceiling.
	 * @param callable(OtpHash): bool $matches Constant-time code comparison.
	 */
	public function verify_latest(
		LookupDigest $email_lookup,
		DateTimeImmutable $now,
		int $maximum_attempts,
		callable $matches
	): ActivationOtpVerificationResult;

	/**
	 * Revoke one unissued challenge after queue failure.
	 *
	 * @param int               $challenge_id Positive challenge identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function revoke_unissued( int $challenge_id, DateTimeImmutable $now ): void;

	/**
	 * Delete a bounded set of expired Account challenges.
	 *
	 * @param DateTimeImmutable $before Exclusive UTC retention boundary.
	 * @param int               $limit Maximum rows removed.
	 */
	public function cleanup_expired( DateTimeImmutable $before, int $limit ): int;
}
