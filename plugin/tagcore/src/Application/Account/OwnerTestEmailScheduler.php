<?php
/**
 * Owner Test Email scheduler port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

interface OwnerTestEmailScheduler {
	/**
	 * Schedule an identifier-only dispatch.
	 *
	 * @param int $event_id Audit Event identifier.
	 * @param int $owner_id Server-derived Owner identifier.
	 */
	public function schedule( int $event_id, int $owner_id ): void;
}
