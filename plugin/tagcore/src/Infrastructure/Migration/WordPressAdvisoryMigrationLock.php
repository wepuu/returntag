<?php
/**
 * MariaDB/MySQL advisory migration lock.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/**
 * Uses GET_LOCK() to serialize migrations for the current site and prefix.
 */
final class WordPressAdvisoryMigrationLock implements MigrationLock {
	/**
	 * WordPress database adapter.
	 *
	 * @var wpdb
	 */
	private wpdb $database;

	/**
	 * Hashed site-specific advisory lock name.
	 *
	 * @var string
	 */
	private string $lock_name;

	/**
	 * Maximum number of seconds to wait for the lock.
	 *
	 * @var int
	 */
	private int $timeout_seconds;

	/**
	 * Whether this instance currently owns the lock.
	 *
	 * @var bool
	 */
	private bool $acquired = false;

	/**
	 * Build a site-specific lock without exposing the raw table prefix.
	 *
	 * @param wpdb $database        WordPress database adapter.
	 * @param int  $site_id         Current WordPress site ID.
	 * @param int  $timeout_seconds Maximum lock wait in seconds.
	 */
	public function __construct( wpdb $database, int $site_id, int $timeout_seconds = 5 ) {
		$this->database        = $database;
		$this->timeout_seconds = max( 0, $timeout_seconds );
		$this->lock_name       = 'returntag_migrate_' . substr( hash( 'sha256', $site_id . ':' . $database->prefix ), 0, 32 );
	}

	/**
	 * Attempt to obtain the named database lock.
	 */
	public function acquire(): bool {
		if ( $this->acquired ) {
			return true;
		}

		$query = $this->database->prepare(
			'SELECT GET_LOCK(%s, %d)',
			$this->lock_name,
			$this->timeout_seconds
		);

		$this->acquired = '1' === (string) $this->database->get_var( $query );

		return $this->acquired;
	}

	/**
	 * Release the named lock only when this instance obtained it.
	 */
	public function release(): void {
		if ( ! $this->acquired ) {
			return;
		}

		$query = $this->database->prepare( 'SELECT RELEASE_LOCK(%s)', $this->lock_name );
		$this->database->get_var( $query );
		$this->acquired = false;
	}
}
