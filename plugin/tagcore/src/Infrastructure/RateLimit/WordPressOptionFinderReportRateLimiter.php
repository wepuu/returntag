<?php
/**
 * Atomic Finder Report rate limiter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\RateLimit;

use DateTimeImmutable;
use InvalidArgumentException;
use ReturnTag\TagCore\Application\FinderReport\FinderReportRateLimiter;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceException;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Domain\Tag\TagId;
use wpdb;

/** Reserves Tag, peer, risk-token, and global fixed-window budgets together. */
final class WordPressOptionFinderReportRateLimiter implements FinderReportRateLimiter {
	public const OPTION_PREFIX = 'returntag_finder_report_rate_';

	private const LOCK_TIMEOUT_SECONDS = 2;

	/**
	 * Create the site-scoped limiter.
	 *
	 * @param wpdb $database Active database connection.
	 * @param int  $site_id Current site identifier.
	 * @throws InvalidArgumentException When site ID is invalid.
	 */
	public function __construct( private readonly wpdb $database, private readonly int $site_id ) {
		if ( $this->site_id < 1 ) {
			throw new InvalidArgumentException( 'Site identifier is invalid.' );
		}
	}

	/**
	 * Reserve all fixed-window budgets atomically.
	 *
	 * @param TagId             $tag_id Server-resolved Tag.
	 * @param LookupDigest      $peer_lookup Keyed peer lookup.
	 * @param LookupDigest      $risk_lookup Keyed risk lookup.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @throws PersistenceException When the limiter is unavailable.
	 */
	public function reserve(
		TagId $tag_id,
		LookupDigest $peer_lookup,
		LookupDigest $risk_lookup,
		DateTimeImmutable $now
	): bool {
		$key = wp_salt( 'auth' );

		if ( '' === $key ) {
			throw new PersistenceException( 'Finder Report rate limiter is unavailable.' );
		}

		$tag_lookup = hash_hmac( 'sha256', "returntag:finder-report:tag:v1\0" . $tag_id->value, $key );
		$timestamp  = $now->getTimestamp();
		$buckets    = array(
			$this->bucket( 'tag:' . $tag_lookup, MINUTE_IN_SECONDS, 5, $timestamp ),
			$this->bucket( 'tag:' . $tag_lookup, HOUR_IN_SECONDS, 20, $timestamp ),
			$this->bucket( 'peer:' . $peer_lookup->value, MINUTE_IN_SECONDS, 5, $timestamp ),
			$this->bucket( 'peer:' . $peer_lookup->value, HOUR_IN_SECONDS, 30, $timestamp ),
			$this->bucket( 'risk:' . $risk_lookup->value, HOUR_IN_SECONDS, 20, $timestamp ),
			$this->bucket( 'global', MINUTE_IN_SECONDS, 60, $timestamp ),
			$this->bucket( 'global', HOUR_IN_SECONDS, 1000, $timestamp ),
		);
		$lock_name  = substr( 'returntag_finder_report_rate_' . hash( 'sha256', (string) $this->site_id ), 0, 64 );

		if ( ! $this->acquire_lock( $lock_name ) ) {
			throw new PersistenceException( 'Finder Report rate limiter is unavailable.' );
		}

		try {
			$current = array();

			foreach ( $buckets as $bucket ) {
				wp_cache_delete( $bucket['option'], 'options' );
				$value = get_option( $bucket['option'], null );
				$count = $this->count_for_bucket( $value, $bucket['expires_at'] );

				if ( $count >= $bucket['limit'] ) {
					return false;
				}

				$current[] = array( $bucket, $count );
			}

			foreach ( $current as [ $bucket, $count ] ) {
				$value = array(
					'count'      => $count + 1,
					'expires_at' => $bucket['expires_at'],
				);

				if ( false === get_option( $bucket['option'], false ) ) {
					if ( ! add_option( $bucket['option'], $value, '', false ) ) {
						throw new PersistenceException( 'Finder Report rate limiter is unavailable.' );
					}
				} elseif ( ! update_option( $bucket['option'], $value, false ) ) {
					throw new PersistenceException( 'Finder Report rate limiter is unavailable.' );
				}
			}

			return true;
		} finally {
			$this->release_lock( $lock_name );
		}
	}

	/**
	 * Build one fixed-window bucket.
	 *
	 * @param string $scope Bounded bucket scope.
	 * @param int    $seconds Window seconds.
	 * @param int    $limit Window limit.
	 * @param int    $timestamp Current timestamp.
	 * @return array{option: string, limit: int, expires_at: int}
	 */
	private function bucket( string $scope, int $seconds, int $limit, int $timestamp ): array {
		$start = intdiv( $timestamp, $seconds ) * $seconds;
		$end   = $start + $seconds + 60;

		return array(
			'option'     => self::OPTION_PREFIX . $end . '_' . hash( 'sha256', $scope . ':' . $seconds . ':' . $start ),
			'limit'      => $limit,
			'expires_at' => $end,
		);
	}

	/**
	 * Read one valid stored count.
	 *
	 * @param mixed $value Stored option value.
	 * @param int   $expiry Expected expiry.
	 */
	private function count_for_bucket( mixed $value, int $expiry ): int {
		if ( ! is_array( $value ) || ( $value['expires_at'] ?? null ) !== $expiry ) {
			return 0;
		}

		$count = $value['count'] ?? null;

		return is_int( $count ) && $count >= 0 ? $count : 0;
	}

	/**
	 * Acquire the site-wide limiter lock.
	 *
	 * @param string $name Lock name.
	 */
	private function acquire_lock( string $name ): bool {
		$query = $this->database->prepare( 'SELECT GET_LOCK(%s, %d)', $name, self::LOCK_TIMEOUT_SECONDS );

		if ( ! is_string( $query ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Prepared above; connection-scoped synchronization.
		return '1' === (string) $this->database->get_var( $query );
	}

	/**
	 * Release the site-wide limiter lock.
	 *
	 * @param string $name Lock name.
	 */
	private function release_lock( string $name ): void {
		$query = $this->database->prepare( 'SELECT RELEASE_LOCK(%s)', $name );

		if ( is_string( $query ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Prepared above; best-effort lock release.
			$this->database->get_var( $query );
		}
	}
}
