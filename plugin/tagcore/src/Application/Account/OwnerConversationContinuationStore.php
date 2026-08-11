<?php
/**
 * Atomic Account-to-Secure-Reply session port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Value\AccessTokenDigest;

/** Rechecks current active ownership and the complete relay eligibility graph. */
interface OwnerConversationContinuationStore {
	/**
	 * Revoke prior Owner sessions and issue one replacement atomically.
	 *
	 * @param int               $conversation_id Browser-selected Conversation candidate.
	 * @param int               $owner_id Current WordPress Owner identifier.
	 * @param AccessTokenDigest $session New session digest.
	 * @param DateTimeImmutable $expires_at Session expiry.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function issue_owner_session(
		int $conversation_id,
		int $owner_id,
		AccessTokenDigest $session,
		DateTimeImmutable $expires_at,
		DateTimeImmutable $now
	): bool;
}
