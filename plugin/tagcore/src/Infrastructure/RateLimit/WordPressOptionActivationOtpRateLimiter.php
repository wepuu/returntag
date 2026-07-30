<?php
/**
 * Durable WordPress Option activation OTP rate limiter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\RateLimit;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Auth\ActivationOtpRateLimiter;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceException;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Domain\Tag\TagId;
use wpdb;

/**
 * Atomically reserves IP and site-wide budgets under one site-scoped lock.
 */
final class WordPressOptionActivationOtpRateLimiter implements ActivationOtpRateLimiter {
	public const OPTION_PREFIX = 'returntag_otp_rate_';

	private const LOCK_TIMEOUT_SECONDS = 2;

	/**
	 * Create the site-scoped limiter.
	 *
	 * @param wpdb $database Active WordPress database connection.
	 * @param int  $site_id Current site identifier.
	 * @throws \InvalidArgumentException When the site identifier is invalid.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly int $site_id
	) {
		if ( $site_id < 1 ) {
			throw new \InvalidArgumentException( 'Site identifier is invalid.' );
		}
	}

	/**
	 * Reserve every IP and global time bucket atomically.
	 *
	 * @param LookupDigest      $ip_lookup Keyed IP digest.
	 * @param LookupDigest      $email_lookup Keyed email digest.
	 * @param TagId             $tag_id Public Tag.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @throws PersistenceException When the limiter cannot reserve safely.
	 */
	public function reserve(
		LookupDigest $ip_lookup,
		LookupDigest $email_lookup,
		TagId $tag_id,
		DateTimeImmutable $now
	): bool {
		$timestamp = $now->getTimestamp();
		$buckets   = array(
			$this->bucket( 'ip:' . $ip_lookup->value, 60, 5, $timestamp ),
			$this->bucket( 'ip:' . $ip_lookup->value, 3600, 30, $timestamp ),
			$this->bucket( 'email:' . $email_lookup->value, 60, 1, $timestamp ),
			$this->bucket( 'email:' . $email_lookup->value, 3600, 5, $timestamp ),
			$this->bucket( 'email:' . $email_lookup->value, DAY_IN_SECONDS, 10, $timestamp ),
			$this->bucket( 'tag:' . $tag_id->value, 60, 3, $timestamp ),
			$this->bucket( 'tag:' . $tag_id->value, 3600, 20, $timestamp ),
			$this->bucket( 'tag:' . $tag_id->value, DAY_IN_SECONDS, 50, $timestamp ),
			$this->bucket( 'global', 60, 60, $timestamp ),
			$this->bucket( 'global', 3600, 1000, $timestamp ),
		);
		$lock_name = substr( 'returntag_otp_rate_' . hash( 'sha256', (string) $this->site_id ), 0, 64 );

		if ( ! $this->acquire_lock( $lock_name ) ) {
			throw new PersistenceException( 'OTP rate limiter is unavailable.' );
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
						throw new PersistenceException( 'OTP rate limiter could not reserve capacity.' );
					}
				} elseif ( ! update_option( $bucket['option'], $value, false ) ) {
					throw new PersistenceException( 'OTP rate limiter could not reserve capacity.' );
				}
			}

			return true;
		} finally {
			$this->release_lock( $lock_name );
		}
	}

	/**
	 * Delete a bounded number of expired high-cardinality options.
	 *
	 * @param int $limit Maximum options inspected.
	 * @throws PersistenceException When cleanup storage is unavailable.
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
			throw new PersistenceException( 'OTP rate-limit cleanup is unavailable.' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above; bounded cleanup of plugin-owned options.
		$names = $this->database->get_col( $query );

		$deleted = 0;
		$now     = time();

		foreach ( $names as $name ) {
			if ( ! is_string( $name ) || ! str_starts_with( $name, self::OPTION_PREFIX ) ) {
				continue;
			}

			$value = get_option( $name, null );

			if ( is_array( $value ) && isset( $value['expires_at'] ) && is_int( $value['expires_at'] ) && $value['expires_at'] <= $now ) {
				$deleted += delete_option( $name ) ? 1 : 0;
			}
		}

		return $deleted;
	}

	/**
	 * Build one deterministic fixed-window bucket.
	 *
	 * @param string $scope Privacy-safe limiter scope.
	 * @param int    $seconds Window size.
	 * @param int    $limit Request limit.
	 * @param int    $timestamp Current Unix time.
	 * @return array{option: string, limit: int, expires_at: int}
	 */
	private function bucket( string $scope, int $seconds, int $limit, int $timestamp ): array {
		$window_start = intdiv( $timestamp, $seconds ) * $seconds;
		$expires_at   = $window_start + $seconds + 60;

		return array(
			'option'     => self::OPTION_PREFIX . $expires_at . '_' . hash( 'sha256', $scope . ':' . $seconds . ':' . $window_start ),
			'limit'      => $limit,
			'expires_at' => $expires_at,
		);
	}

	/**
	 * Read a valid bucket count or reset malformed/expired state.
	 *
	 * @param mixed $value Stored Option value.
	 * @param int   $expected_expiry Expected bucket expiry.
	 */
	private function count_for_bucket( mixed $value, int $expected_expiry ): int {
		if ( ! is_array( $value ) || ( $value['expires_at'] ?? null ) !== $expected_expiry ) {
			return 0;
		}

		$count = $value['count'] ?? null;

		return is_int( $count ) && $count >= 0 ? $count : 0;
	}

	/**
	 * Acquire the site-scoped database lock.
	 *
	 * @param string $lock_name Trusted lock name.
	 */
	private function acquire_lock( string $lock_name ): bool {
		$query = $this->database->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, self::LOCK_TIMEOUT_SECONDS );

		if ( ! is_string( $query ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above; connection-scoped synchronization.
		return '1' === (string) $this->database->get_var( $query );
	}

	/**
	 * Release the site-scoped database lock.
	 *
	 * @param string $lock_name Trusted lock name.
	 */
	private function release_lock( string $lock_name ): void {
		$query = $this->database->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name );

		if ( is_string( $query ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above; best-effort lock release.
			$this->database->get_var( $query );
		}
	}
}
