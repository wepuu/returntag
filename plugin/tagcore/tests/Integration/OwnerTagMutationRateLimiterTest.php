<?php
/**
 * RT-317 Stage 2 mutation rate-limit integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionOwnerTagMutationRateLimiter;
use WP_UnitTestCase;

/** Verifies durable, non-autoloaded Owner and Tag mutation budgets. */
final class OwnerTagMutationRateLimiterTest extends WP_UnitTestCase {
	/**
	 * Created Option names.
	 *
	 * @var list<string>
	 */
	private array $options = array();

	/** One Tag receives thirty hourly attempts and rejects the thirty-first. */
	public function test_tag_budget_is_bounded_and_not_autoloaded(): void {
		global $wpdb;

		$owner_id      = 42;
		$tag_id        = TagId::from_canonical( 'A7R2W9' );
		$now           = new DateTimeImmutable( '2026-08-10 12:00:00', new DateTimeZone( 'UTC' ) );
		$limiter       = new WordPressOptionOwnerTagMutationRateLimiter( $wpdb, get_current_blog_id() );
		$window        = intdiv( $now->getTimestamp(), HOUR_IN_SECONDS ) * HOUR_IN_SECONDS;
		$expires       = $window + HOUR_IN_SECONDS + MINUTE_IN_SECONDS;
		$site_id       = get_current_blog_id();
		$owner         = hash( 'sha256', $site_id . ':owner:' . $owner_id . ':' . $window );
		$tag           = hash( 'sha256', $site_id . ':tag:' . $owner_id . ':' . $tag_id->value . ':' . $window );
		$this->options = array(
			WordPressOptionOwnerTagMutationRateLimiter::OPTION_PREFIX . $expires . '_' . $owner,
			WordPressOptionOwnerTagMutationRateLimiter::OPTION_PREFIX . $expires . '_' . $tag,
		);

		for ( $attempt = 0; $attempt < 30; ++$attempt ) {
			self::assertTrue( $limiter->reserve( $owner_id, $tag_id, $now ) );
		}

		self::assertFalse( $limiter->reserve( $owner_id, $tag_id, $now ) );

		foreach ( $this->options as $name ) {
			$value = get_option( $name );
			self::assertIsArray( $value );
			self::assertFalse( in_array( $this->autoload_value( $name ), array( 'yes', 'on', 'auto-on' ), true ) );
		}
	}

	/** Delete only Options created by this synthetic test. */
	protected function tearDown(): void {
		foreach ( $this->options as $name ) {
			delete_option( $name );
		}

		parent::tearDown();
	}

	/**
	 * Read one test Option autoload value.
	 *
	 * @param string $name Exact synthetic Option name.
	 */
	private function autoload_value( string $name ): string {
		global $wpdb;

		$query = $wpdb->prepare( 'SELECT autoload FROM %i WHERE option_name = %s', $wpdb->options, $name );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared test-only inspection of one exact synthetic Option.
		return (string) $wpdb->get_var( $query );
	}
}
