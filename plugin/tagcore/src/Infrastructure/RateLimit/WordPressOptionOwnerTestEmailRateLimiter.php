<?php
/**
 * WordPress Option Owner Test Email rate limiter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\RateLimit;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Account\OwnerTestEmailRateLimiter;

/** Persists only opaque hourly user/IP counters, never an address. */
final class WordPressOptionOwnerTestEmailRateLimiter implements OwnerTestEmailRateLimiter {
	/**
	 * Reserve one opaque hourly Owner and peer budget.
	 *
	 * @param int               $owner_id Server-derived Owner identifier.
	 * @param string            $ip_address Direct-peer IP address.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve( int $owner_id, string $ip_address, DateTimeImmutable $now ): bool {
		if ( $owner_id < 1 || false === inet_pton( $ip_address ) ) {
			return false; }
		$window = intdiv( $now->getTimestamp(), HOUR_IN_SECONDS );
		$key    = 'returntag_owner_test_email_rate_' . hash( 'sha256', get_current_blog_id() . ':' . $owner_id . ':' . inet_pton( $ip_address ) . ':' . $window );
		$count  = get_option( $key, 0 );
		if ( ! is_int( $count ) || $count >= 3 ) {
			return false; }
		return 0 === $count ? add_option( $key, 1, '', false ) : update_option( $key, $count + 1, false );
	}
}
