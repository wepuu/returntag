<?php
/**
 * Fail-closed transactional email gateway.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Email;

use ReturnTag\TagCore\Application\Email\TransactionalEmail;
use ReturnTag\TagCore\Application\Email\TransactionalEmailGateway;
use ReturnTag\TagCore\Application\Email\TransactionalEmailResult;

/** Preserves non-email runtimes when external configuration is unavailable. */
final class UnavailableTransactionalEmailGateway implements TransactionalEmailGateway {
	/**
	 * Always fail without side effects or fallback.
	 *
	 * @param TransactionalEmail $email Private request intentionally not inspected.
	 */
	public function send( TransactionalEmail $email ): TransactionalEmailResult {
		return TransactionalEmailResult::failed();
	}
}
