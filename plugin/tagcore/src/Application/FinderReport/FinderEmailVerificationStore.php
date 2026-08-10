<?php
/**
 * Finder email verification persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Record\AuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewAuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;

/** Atomic persistence operations for Finder email verification. */
interface FinderEmailVerificationStore {
	/**
	 * @param LookupDigest      $lookup Keyed email lookup.
	 * @param DateTimeImmutable $since Inclusive UTC boundary.
	 */
	public function count_recent_for_email( LookupDigest $lookup, DateTimeImmutable $since ): int;
	/**
	 * @param int               $finder_report_id Internal report identifier.
	 * @param DateTimeImmutable $since Inclusive UTC boundary.
	 */
	public function count_recent_for_report( int $finder_report_id, DateTimeImmutable $since ): int;
	/** @param NewAuthChallengeRecord $challenge New placeholder challenge. */
	public function create_replacing( NewAuthChallengeRecord $challenge ): AuthChallengeRecord;
	/** @param int $challenge_id Internal challenge identifier. */
	public function find_by_id( int $challenge_id ): ?AuthChallengeRecord;
	/**
	 * @param int               $challenge_id Internal challenge identifier.
	 * @param OtpHash           $hash Issued hash.
	 * @param DateTimeImmutable $expires_at UTC expiry.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function claim_for_dispatch( int $challenge_id, OtpHash $hash, DateTimeImmutable $expires_at, DateTimeImmutable $now ): ?AuthChallengeRecord;
	/**
	 * @param int               $finder_report_id Internal report identifier.
	 * @param LookupDigest      $lookup Email lookup.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param int               $maximum_attempts Attempt ceiling.
	 * @param callable          $matches Hash matcher.
	 * @param callable          $on_verified Atomic success mutation.
	 */
	public function verify_latest( int $finder_report_id, LookupDigest $lookup, DateTimeImmutable $now, int $maximum_attempts, callable $matches, callable $on_verified ): ?AuthChallengeRecord;
	/**
	 * @param int               $challenge_id Internal challenge identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function revoke_unissued( int $challenge_id, DateTimeImmutable $now ): void;
}
