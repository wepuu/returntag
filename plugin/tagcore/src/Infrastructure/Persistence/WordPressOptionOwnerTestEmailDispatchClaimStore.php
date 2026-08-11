<?php
/**
 * WordPress Option Owner Test Email dispatch claims.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Account\OwnerTestEmailDispatchClaimStore;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceException;
use wpdb;

/** Uses non-autoloaded opaque Options for bounded at-most-once claims. */
final readonly class WordPressOptionOwnerTestEmailDispatchClaimStore implements OwnerTestEmailDispatchClaimStore {
	private const OPTION_PREFIX = 'returntag_owner_test_email_claim_';

	/**
	 * Create the site-scoped claim Store.
	 *
	 * @param wpdb $database WordPress database adapter.
	 * @param int  $site_id Current site identifier.
	 */
	public function __construct( private wpdb $database, private int $site_id ) {}

	/**
	 * Atomically claim one request Event for seven days.
	 *
	 * @param int               $event_id Request Event identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function claim( int $event_id, DateTimeImmutable $now ): bool {
		if ( $event_id < 1 ) {
			return false;
		}
		return add_option(
			$this->name( $event_id ),
			array( 'expires_at' => $now->getTimestamp() + WEEK_IN_SECONDS ),
			'',
			false
		);
	}

	/**
	 * Delete a bounded number of expired opaque claims.
	 *
	 * @param int $limit Maximum candidate Options to inspect.
	 * @throws PersistenceException When cleanup storage cannot be queried.
	 */
	public function cleanup_expired( int $limit = 500 ): int {
		$limit = max( 1, min( 500, $limit ) );
		$like  = $this->database->esc_like( self::OPTION_PREFIX ) . '%';
		$query = $this->database->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s ORDER BY option_name ASC LIMIT %d', $this->database->options, $like, $limit );
		if ( ! is_string( $query ) ) {
			throw new PersistenceException( 'Owner Test Email claim cleanup is unavailable.' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared bounded cleanup of plugin-owned Options.
		$names   = $this->database->get_col( $query );
		$now     = time();
		$deleted = 0;
		foreach ( $names as $name ) {
			if ( ! is_string( $name ) || ! str_starts_with( $name, self::OPTION_PREFIX ) ) {
				continue;
			}
			$value = get_option( $name, null );
			if ( is_array( $value ) && is_int( $value['expires_at'] ?? null ) && $value['expires_at'] <= $now ) {
				$deleted += delete_option( $name ) ? 1 : 0;
			}
		}
		return $deleted;
	}

	/**
	 * Build one site-scoped opaque Option name.
	 *
	 * @param int $event_id Request Event identifier.
	 */
	private function name( int $event_id ): string {
		return self::OPTION_PREFIX . hash( 'sha256', $this->site_id . ':' . $event_id );
	}
}
