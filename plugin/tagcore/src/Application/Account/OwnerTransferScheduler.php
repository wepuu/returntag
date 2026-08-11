<?php
/**
 * Owner Transfer scheduler port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

interface OwnerTransferScheduler {
	/**
	 * Schedule one internal Transfer identifier.
	 *
	 * @param int $transfer_id Internal Transfer identifier.
	 */
	public function schedule( int $transfer_id ): void;
}
