<?php
/**
 * Durable Owner Tag mutation rate limiter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\RateLimit;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Account\OwnerTagMutationRateLimiter;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceException;
use ReturnTag\TagCore\Domain\Tag\TagId;
use wpdb;

/** Reserves site-, Owner-, and Tag-scoped fixed-window mutation budgets. */
final readonly class WordPressOptionOwnerTagMutationRateLimiter implements OwnerTagMutationRateLimiter {
	public const OPTION_PREFIX = 'returntag_owner_tag_rate_';

	private const LOCK_TIMEOUT_SECONDS = 2;

	/**
	 * Create the site-scoped limiter.
	 *
	 * @param wpdb $database WordPress database connection.
	 * @param int  $site_id Positive site identifier.
	 * @throws \InvalidArgumentException When the site identifier is invalid.
	 */
	public function __construct(
		private wpdb $database,
		private int $site_id
	) {
		if ( $site_id < 1 ) {
			throw new \InvalidArgumentException( 'Site identifier is invalid.' );
		}
	}

	/**
	 * Reserve 30 hourly Tag writes and 100 hourly Owner writes.
	 *
	 * @param int               $owner_id Current Owner identifier.
	 * @param TagId             $tag_id Selected public Tag identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @throws \InvalidArgumentException When the Owner identifier is invalid.
	 * @throws PersistenceException When the durable budget is unavailable.
	 */
	public function reserve( int $owner_id, TagId $tag_id, DateTimeImmutable $now ): bool {
		if ( $owner_id < 1 ) {
			throw new \InvalidArgumentException( 'Owner identifier is invalid.' );
		}

		$window  = intdiv( $now->getTimestamp(), HOUR_IN_SECONDS ) * HOUR_IN_SECONDS;
		$expires = $window + HOUR_IN_SECONDS + MINUTE_IN_SECONDS;
		$owner   = hash( 'sha256', $this->site_id . ':owner:' . $owner_id . ':' . $window );
		$tag     = hash( 'sha256', $this->site_id . ':tag:' . $owner_id . ':' . $tag_id->value . ':' . $window );
		$buckets = array(
			array(
				'name'  => self::OPTION_PREFIX . $expires . '_' . $owner,
				'limit' => 100,
			),
			array(
				'name'  => self::OPTION_PREFIX . $expires . '_' . $tag,
				'limit' => 30,
			),
		);
		$lock    = substr( 'returntag_owner_tag_' . hash( 'sha256', (string) $this->site_id ), 0, 64 );

		if ( ! $this->acquire_lock( $lock ) ) {
			throw new PersistenceException( 'Owner Tag rate limiter is unavailable.' );
		}

		try {
			$counts = array();

			foreach ( $buckets as $bucket ) {
				wp_cache_delete( $bucket['name'], 'options' );
				$value = get_option( $bucket['name'], null );
				$count = is_array( $value ) && ( $value['expires_at'] ?? null ) === $expires && is_int( $value['count'] ?? null )
					? max( 0, $value['count'] )
					: 0;

				if ( $count >= $bucket['limit'] ) {
					return false;
				}

				$counts[] = array( $bucket['name'], $count );
			}

			foreach ( $counts as [ $name, $count ] ) {
				$value = array(
					'count'      => $count + 1,
					'expires_at' => $expires,
				);

				if ( false === get_option( $name, false ) ) {
					if ( ! add_option( $name, $value, '', false ) ) {
						throw new PersistenceException( 'Owner Tag rate limit could not be reserved.' );
					}
				} elseif ( ! update_option( $name, $value, false ) ) {
					throw new PersistenceException( 'Owner Tag rate limit could not be reserved.' );
				}
			}

			return true;
		} finally {
			$this->release_lock( $lock );
		}
	}

	/**
	 * Delete a bounded number of expired mutation budget Options.
	 *
	 * @param int $limit Maximum candidate Options to inspect.
	 * @throws PersistenceException When the cleanup query cannot be prepared.
	 */
	public function cleanup_expired( int $limit = 500 ): int {
		$limit = max( 1, min( 500, $limit ) );
		$like  = $this->database->esc_like( self::OPTION_PREFIX ) . '%';
		$query = $this->database->prepare(
			'SELECT option_name FROM %i WHERE option_name LIKE %s ORDER BY option_name ASC LIMIT %d',
			$this->database->options,
			$like,
			$limit
		);

		if ( ! is_string( $query ) ) {
			throw new PersistenceException( 'Owner Tag rate-limit cleanup is unavailable.' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above; bounded cleanup of plugin-owned options.
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
	 * Acquire the site-scoped limiter lock.
	 *
	 * @param string $lock Bounded advisory-lock name.
	 */
	private function acquire_lock( string $lock ): bool {
		$query = $this->database->prepare( 'SELECT GET_LOCK(%s, %d)', $lock, self::LOCK_TIMEOUT_SECONDS );

		if ( ! is_string( $query ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above; connection-scoped synchronization.
		return '1' === (string) $this->database->get_var( $query );
	}

	/**
	 * Release the site-scoped limiter lock.
	 *
	 * @param string $lock Bounded advisory-lock name.
	 */
	private function release_lock( string $lock ): void {
		$query = $this->database->prepare( 'SELECT RELEASE_LOCK(%s)', $lock );

		if ( is_string( $query ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above; best-effort release.
			$this->database->get_var( $query );
		}
	}
}
