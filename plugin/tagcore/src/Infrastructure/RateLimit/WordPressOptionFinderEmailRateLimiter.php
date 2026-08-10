<?php
/**
 * WordPress Finder email verification limiter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\RateLimit;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\FinderReport\FinderEmailRateLimiter;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceException;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use wpdb;

/** Site-scoped atomic fixed-window limiter with opaque option keys. */
final readonly class WordPressOptionFinderEmailRateLimiter implements FinderEmailRateLimiter {
	public const OPTION_PREFIX = 'returntag_finder_email_rate_';

	/**
	 * Create one site-scoped limiter.
	 *
	 * @param wpdb $database Active database connection.
	 * @param int  $site_id Current site identifier.
	 * @throws \InvalidArgumentException When the site identifier is invalid.
	 */
	public function __construct( private wpdb $database, private int $site_id ) {
		if ( $site_id < 1 ) {
			throw new \InvalidArgumentException( 'Site identifier is invalid.' );
		}
	}

	/**
	 * Reserve email and peer budgets atomically.
	 *
	 * @param LookupDigest      $email Keyed email lookup.
	 * @param LookupDigest      $peer Keyed peer lookup.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @throws PersistenceException When synchronization or storage fails.
	 */
	public function reserve_request( LookupDigest $email, LookupDigest $peer, DateTimeImmutable $now ): bool {
		return $this->reserve(
			array(
				$this->bucket( 'request-email:' . $email->value, 60, 2, $now->getTimestamp() ),
				$this->bucket( 'request-email:' . $email->value, 3600, 5, $now->getTimestamp() ),
				$this->bucket( 'request-peer:' . $peer->value, 60, 5, $now->getTimestamp() ),
				$this->bucket( 'request-peer:' . $peer->value, 3600, 20, $now->getTimestamp() ),
			)
		);
	}

	/**
	 * Reserve verification-attempt budgets atomically.
	 *
	 * @param LookupDigest      $email Keyed email lookup.
	 * @param LookupDigest      $peer Keyed peer lookup.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve_verification( LookupDigest $email, LookupDigest $peer, DateTimeImmutable $now ): bool {
		return $this->reserve(
			array(
				$this->bucket( 'verify-email:' . $email->value, 60, 5, $now->getTimestamp() ),
				$this->bucket( 'verify-email:' . $email->value, 3600, 20, $now->getTimestamp() ),
				$this->bucket( 'verify-peer:' . $peer->value, 60, 10, $now->getTimestamp() ),
				$this->bucket( 'verify-peer:' . $peer->value, 3600, 50, $now->getTimestamp() ),
			)
		);
	}

	/**
	 * Reserve relay session, peer, and Conversation budgets atomically.
	 *
	 * @param LookupDigest      $session Keyed session lookup.
	 * @param LookupDigest      $peer Keyed peer lookup.
	 * @param int               $conversation_id Conversation identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @throws \InvalidArgumentException When the Conversation identifier is invalid.
	 */
	public function reserve_conversation_message( LookupDigest $session, LookupDigest $peer, int $conversation_id, DateTimeImmutable $now ): bool {
		if ( $conversation_id < 1 ) {
			throw new \InvalidArgumentException( 'Conversation identifier is invalid.' );
		}
		return $this->reserve(
			array(
				$this->bucket( 'message-session:' . $session->value, 60, 5, $now->getTimestamp() ),
				$this->bucket( 'message-session:' . $session->value, 3600, 20, $now->getTimestamp() ),
				$this->bucket( 'message-peer:' . $peer->value, 60, 10, $now->getTimestamp() ),
				$this->bucket( 'message-peer:' . $peer->value, 3600, 50, $now->getTimestamp() ),
				$this->bucket( 'message-conversation:' . $conversation_id, 60, 10, $now->getTimestamp() ),
				$this->bucket( 'message-conversation:' . $conversation_id, 3600, 20, $now->getTimestamp() ),
			)
		);
	}

	/**
	 * Delete a bounded number of expired buckets.
	 *
	 * @param int $limit Maximum options inspected.
	 * @throws PersistenceException When cleanup cannot be queried safely.
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
			throw new PersistenceException( 'Finder email rate-limit cleanup is unavailable.' );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared bounded cleanup of plugin-owned options.
		$names = $this->database->get_col( $query );
		$now   = time();
		$count = 0;
		foreach ( $names as $name ) {
			if ( ! is_string( $name ) || ! str_starts_with( $name, self::OPTION_PREFIX ) ) {
				continue;
			}
			$value = get_option( $name, null );
			if ( is_array( $value ) && isset( $value['expires_at'] ) && is_int( $value['expires_at'] ) && $value['expires_at'] <= $now ) {
				$count += delete_option( $name ) ? 1 : 0;
			}
		}
		return $count;
	}

	/**
	 * Reserve one set of prepared buckets under the site lock.
	 *
	 * @param list<array{option: string, limit: int, expires_at: int}> $buckets Fixed-window buckets.
	 * @throws PersistenceException When synchronization or storage fails.
	 */
	private function reserve( array $buckets ): bool {
		$lock  = substr( 'returntag_finder_email_' . hash( 'sha256', (string) $this->site_id ), 0, 64 );
		$query = $this->database->prepare( 'SELECT GET_LOCK(%s, %d)', $lock, 2 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared synchronization query.
		if ( ! is_string( $query ) || '1' !== (string) $this->database->get_var( $query ) ) {
			throw new PersistenceException( 'Finder email rate limiter is unavailable.' );
		}
		try {
			$current = array();
			foreach ( $buckets as $bucket ) {
				wp_cache_delete( $bucket['option'], 'options' );
				$value = get_option( $bucket['option'], null );
				$count = is_array( $value ) && ( $value['expires_at'] ?? null ) === $bucket['expires_at'] && is_int( $value['count'] ?? null ) ? $value['count'] : 0;
				if ( $count >= $bucket['limit'] ) {
					return false;
				}
				$current[] = array( $bucket, $count );
			}
			foreach ( $current as [ $bucket, $count ] ) {
				$value  = array(
					'count'      => $count + 1,
					'expires_at' => $bucket['expires_at'],
				);
				$stored = false === get_option( $bucket['option'], false ) ? add_option( $bucket['option'], $value, '', false ) : update_option( $bucket['option'], $value, false );
				if ( ! $stored ) {
					throw new PersistenceException( 'Finder email rate limit could not be reserved.' );
				}
			}
			return true;
		} finally {
			$release = $this->database->prepare( 'SELECT RELEASE_LOCK(%s)', $lock );
			if ( is_string( $release ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared lock release.
				$this->database->get_var( $release );
			}
		}
	}

	/**
	 * Build one opaque fixed-window bucket.
	 *
	 * @param string $scope Privacy-safe scope.
	 * @param int    $seconds Window length.
	 * @param int    $limit Request ceiling.
	 * @param int    $timestamp Current Unix time.
	 * @return array{option: string, limit: int, expires_at: int}
	 */
	private function bucket( string $scope, int $seconds, int $limit, int $timestamp ): array {
		$start   = intdiv( $timestamp, $seconds ) * $seconds;
		$expires = $start + $seconds + 60;
		return array(
			'option'     => self::OPTION_PREFIX . $expires . '_' . hash( 'sha256', $scope . '|' . $seconds . '|' . $start ),
			'limit'      => $limit,
			'expires_at' => $expires,
		);
	}
}
