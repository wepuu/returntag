<?php
/**
 * Activation OTP challenge workflow persistence port.
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
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Provides the indexed and atomic challenge operations needed by RT-304.
 */
interface ActivationOtpRequestStore {
	/**
	 * Count recent persistent challenges for one email digest.
	 *
	 * @param LookupDigest      $email_lookup Keyed email digest.
	 * @param DateTimeImmutable $since Inclusive UTC boundary.
	 */
	public function count_recent_for_email( LookupDigest $email_lookup, DateTimeImmutable $since ): int;

	/**
	 * Count recent persistent challenges for one public Tag.
	 *
	 * @param TagId             $tag_id Public Tag.
	 * @param DateTimeImmutable $since Inclusive UTC boundary.
	 */
	public function count_recent_for_tag( TagId $tag_id, DateTimeImmutable $since ): int;

	/**
	 * Consume older matches and insert one unissued challenge.
	 *
	 * @param NewAuthChallengeRecord $challenge Unissued challenge.
	 */
	public function create_replacing( NewAuthChallengeRecord $challenge ): AuthChallengeRecord;

	/**
	 * Find one challenge by internal identifier.
	 *
	 * @param int $challenge_id Positive challenge ID.
	 */
	public function find_by_id( int $challenge_id ): ?AuthChallengeRecord;

	/**
	 * Atomically transition one latest unissued challenge to issued.
	 *
	 * @param int               $challenge_id Positive challenge ID.
	 * @param OtpHash           $code_hash Issued OTP hash.
	 * @param DateTimeImmutable $expires_at Issued-code expiry.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function claim_for_dispatch(
		int $challenge_id,
		OtpHash $code_hash,
		DateTimeImmutable $expires_at,
		DateTimeImmutable $now
	): ?AuthChallengeRecord;

	/**
	 * Revoke an unissued challenge after queue failure.
	 *
	 * @param int               $challenge_id Positive challenge ID.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function revoke_unissued( int $challenge_id, DateTimeImmutable $now ): void;

	/**
	 * Delete a bounded set of expired challenge records after retention.
	 *
	 * @param DateTimeImmutable $before Exclusive UTC retention boundary.
	 * @param int               $limit Maximum rows removed.
	 */
	public function cleanup_expired( DateTimeImmutable $before, int $limit ): int;
}
