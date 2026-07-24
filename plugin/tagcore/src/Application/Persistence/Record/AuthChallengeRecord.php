<?php
/**
 * Stored authentication challenge record.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * One persisted opaque authentication challenge row.
 */
final readonly class AuthChallengeRecord {
	/**
	 * Create a stored challenge record.
	 *
	 * @param int                    $challenge_id Challenge identifier.
	 * @param NewAuthChallengeRecord $data Stored challenge data.
	 */
	public function __construct(
		public int $challenge_id,
		public NewAuthChallengeRecord $data
	) {
		RecordValidator::positive_id( $this->challenge_id, 'challenge_id' );
	}
}
