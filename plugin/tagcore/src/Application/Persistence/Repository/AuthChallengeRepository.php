<?php
/**
 * Authentication Challenge persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Repository;

use ReturnTag\TagCore\Application\Persistence\Record\AuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewAuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;

/**
 * Narrow opaque authentication challenge persistence contract.
 */
interface AuthChallengeRepository {
	/**
	 * Insert one opaque authentication challenge.
	 *
	 * @param NewAuthChallengeRecord $record New challenge data.
	 */
	public function insert( NewAuthChallengeRecord $record ): AuthChallengeRecord;

	/**
	 * Find one challenge by identifier.
	 *
	 * @param int $challenge_id Challenge identifier.
	 */
	public function find_by_id( int $challenge_id ): ?AuthChallengeRecord;

	/**
	 * Find the most recently created structural match.
	 *
	 * Application code remains responsible for validity decisions.
	 *
	 * @param string       $purpose Challenge purpose.
	 * @param LookupDigest $email_lookup Keyed lookup digest.
	 */
	public function find_latest_for_purpose_and_lookup( string $purpose, LookupDigest $email_lookup ): ?AuthChallengeRecord;
}
