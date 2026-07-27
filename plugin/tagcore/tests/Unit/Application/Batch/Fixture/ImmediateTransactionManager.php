<?php
/**
 * Immediate transaction test fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture;

use ReturnTag\TagCore\Application\Persistence\TransactionManager;

/**
 * Executes the callback while recording transaction use.
 */
final class ImmediateTransactionManager implements TransactionManager {
	/**
	 * Number of transaction invocations.
	 *
	 * @var int
	 */
	public int $calls = 0;

	/**
	 * Execute one callback.
	 *
	 * @template T
	 * @param callable(): T $operation Transaction callback.
	 * @return T
	 */
	public function transactional( callable $operation ): mixed {
		++$this->calls;

		return $operation();
	}
}
