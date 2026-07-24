<?php
/**
 * Application transaction boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence;

/**
 * Coordinates explicit database-only operations.
 */
interface TransactionManager {
	/**
	 * Run one non-nested transactional operation.
	 *
	 * @template T
	 * @param callable(): T $operation Transaction callback.
	 * @return T
	 */
	public function transactional( callable $operation ): mixed;
}
