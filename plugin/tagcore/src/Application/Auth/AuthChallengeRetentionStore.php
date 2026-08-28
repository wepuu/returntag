<?php
/**
 * Authentication challenge retention persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use DateTimeImmutable;

/** Deletes bounded sets of challenges that no longer authorize a request. */
interface AuthChallengeRetentionStore {
	/**
	 * Delete challenges that are expired or consumed at the supplied time.
	 *
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param int               $limit Maximum rows removed.
	 */
	public function cleanup_eligible( DateTimeImmutable $now, int $limit ): int;
}
