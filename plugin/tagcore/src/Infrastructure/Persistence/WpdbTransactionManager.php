<?php
/**
 * WordPress database transaction boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use LogicException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceException;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use Throwable;
use wpdb;

/**
 * Executes explicit non-nested transactions on one wpdb connection.
 */
final class WpdbTransactionManager implements TransactionManager {
	/**
	 * Whether this manager currently owns a transaction.
	 *
	 * @var bool
	 */
	private bool $transaction_active = false;

	/**
	 * Create the transaction manager.
	 *
	 * @param wpdb $database Active WordPress database adapter.
	 */
	public function __construct( private readonly wpdb $database ) {
	}

	/**
	 * Run one non-nested transactional operation.
	 *
	 * @template T
	 * @param callable(): T $operation Transaction callback.
	 * @return T
	 * @throws LogicException When this manager already owns a transaction.
	 * @throws PersistenceException When a transaction command fails.
	 * @throws Throwable When the callback or transaction command fails.
	 */
	public function transactional( callable $operation ): mixed {
		if ( $this->transaction_active ) {
			throw new LogicException( 'Nested transactions are not supported.' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit transaction boundary.
		if ( false === $this->database->query( 'START TRANSACTION' ) ) {
			throw new PersistenceException( 'Transaction could not be started.' );
		}

		$this->transaction_active = true;

		try {
			$result = $operation();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit transaction boundary.
			if ( false === $this->database->query( 'COMMIT' ) ) {
				throw new PersistenceException( 'Transaction could not be committed.' );
			}

			return $result;
		} catch ( Throwable $exception ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Best-effort rollback before rethrowing the original failure.
			$this->database->query( 'ROLLBACK' );
			throw $exception;
		} finally {
			$this->transaction_active = false;
		}
	}
}
