<?php
/**
 * Durable Owner Account OTP rate limiter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\RateLimit;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Auth\AccountOtpRateLimiter;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceException;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use wpdb;

/**
 * Uses an Account-specific Option and lock domain.
 */
final class WordPressOptionAccountOtpRateLimiter implements AccountOtpRateLimiter {
	public const OPTION_PREFIX = 'returntag_account_otp_rate_';

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
	 * Reserve request budgets in the Account-specific domain.
	 *
	 * @param LookupDigest      $ip_lookup Keyed direct-peer IP digest.
	 * @param LookupDigest      $email_lookup Keyed email digest.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve_request(
		LookupDigest $ip_lookup,
		LookupDigest $email_lookup,
		DateTimeImmutable $now
	): bool {
		$time = $now->getTimestamp();

		return $this->reserve(
			array(
				$this->bucket( 'request-ip:' . $ip_lookup->value, 60, 5, $time ),
				$this->bucket( 'request-ip:' . $ip_lookup->value, 3600, 30, $time ),
				$this->bucket( 'request-email:' . $email_lookup->value, 60, 1, $time ),
				$this->bucket( 'request-email:' . $email_lookup->value, 3600, 5, $time ),
				$this->bucket( 'request-email:' . $email_lookup->value, DAY_IN_SECONDS, 10, $time ),
				$this->bucket( 'request-global', 60, 60, $time ),
				$this->bucket( 'request-global', 3600, 1000, $time ),
			)
		);
	}

	/**
	 * Reserve public verification budgets before challenge lookup.
	 *
	 * @param LookupDigest      $ip_lookup Keyed direct-peer IP digest.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve_verification_ip( LookupDigest $ip_lookup, DateTimeImmutable $now ): bool {
		$time = $now->getTimestamp();

		return $this->reserve(
			array(
				$this->bucket( 'verify-ip:' . $ip_lookup->value, 60, 10, $time ),
				$this->bucket( 'verify-ip:' . $ip_lookup->value, 3600, 50, $time ),
				$this->bucket( 'verify-global', 60, 300, $time ),
				$this->bucket( 'verify-global', 3600, 5000, $time ),
			)
		);
	}

	/**
	 * Reserve email verification budgets after challenge eligibility.
	 *
	 * @param LookupDigest      $email_lookup Keyed email digest.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve_verification_email( LookupDigest $email_lookup, DateTimeImmutable $now ): bool {
		$time = $now->getTimestamp();

		return $this->reserve(
			array(
				$this->bucket( 'verify-email:' . $email_lookup->value, 60, 10, $time ),
				$this->bucket( 'verify-email:' . $email_lookup->value, 3600, 50, $time ),
			)
		);
	}

	/**
	 * Delete a bounded number of expired Account rate-limit Options.
	 *
	 * @param int $limit Maximum Options inspected.
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
			throw new PersistenceException( 'Account OTP rate-limit cleanup is unavailable.' );
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

			if ( is_array( $value ) && isset( $value['expires_at'] ) && is_int( $value['expires_at'] ) && $value['expires_at'] <= $now ) {
				$deleted += delete_option( $name ) ? 1 : 0;
			}
		}

		return $deleted;
	}

	/**
	 * Reserve one atomic set of fixed-window buckets.
	 *
	 * @param array<int, array{option: string, limit: int, expires_at: int}> $buckets Fixed-window buckets.
	 * @throws PersistenceException When the limiter cannot reserve safely.
	 */
	private function reserve( array $buckets ): bool {
		$lock = substr( 'returntag_account_otp_' . hash( 'sha256', (string) $this->site_id ), 0, 64 );

		if ( ! $this->acquire_lock( $lock ) ) {
			throw new PersistenceException( 'Account OTP rate limiter is unavailable.' );
		}

		try {
			$current = array();

			foreach ( $buckets as $bucket ) {
				wp_cache_delete( $bucket['option'], 'options' );
				$count = $this->count_for_bucket( get_option( $bucket['option'], null ), $bucket['expires_at'] );

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
						throw new PersistenceException( 'Account OTP rate limit could not be reserved.' );
					}
				} elseif ( ! update_option( $bucket['option'], $value, false ) ) {
					throw new PersistenceException( 'Account OTP rate limit could not be reserved.' );
				}
			}

			return true;
		} finally {
			$this->release_lock( $lock );
		}
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
		$window  = intdiv( $timestamp, $seconds ) * $seconds;
		$expires = $window + $seconds + 60;

		return array(
			'option'     => self::OPTION_PREFIX . $expires . '_' . hash( 'sha256', $scope . ':' . $seconds . ':' . $window ),
			'limit'      => $limit,
			'expires_at' => $expires,
		);
	}

	/**
	 * Read one valid bucket count.
	 *
	 * @param mixed $value Stored Option value.
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
	 * Acquire the site-scoped Account limiter lock.
	 *
	 * @param string $lock Trusted lock name.
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
	 * Release the site-scoped Account limiter lock.
	 *
	 * @param string $lock Trusted lock name.
	 */
	private function release_lock( string $lock ): void {
		$query = $this->database->prepare( 'SELECT RELEASE_LOCK(%s)', $lock );

		if ( is_string( $query ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above; best-effort release.
			$this->database->get_var( $query );
		}
	}
}
