<?php
/**
 * Transactional email gateway port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Email;

/** Hides provider and HTTP semantics from business workflows. */
interface TransactionalEmailGateway {
	/**
	 * Submit one idempotent transactional message.
	 *
	 * @param TransactionalEmail $email Private in-memory request.
	 */
	public function send( TransactionalEmail $email ): TransactionalEmailResult;
}
