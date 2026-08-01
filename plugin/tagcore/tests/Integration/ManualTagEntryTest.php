<?php
/**
 * RT-312 manual Tag entry integration coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\PublicTag\SubmitManualTagEntry;
use ReturnTag\TagCore\Application\Tag\TagIdInputNormalizer;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionManualTagEntryRateLimiter;
use ReturnTag\TagCore\Infrastructure\Security\WordPressPublicRequestHasher;
use ReturnTag\TagCore\PublicSite\ManualTagEntryFormHandler;
use ReturnTag\TagCore\PublicSite\ManualTagEntryFormState;
use ReturnTag\TagCore\PublicSite\ManualTagEntryRouteController;
use ReturnTag\TagCore\PublicSite\ManualTagEntryTemplateRenderer;
use ReturnTag\TagCore\PublicSite\PublicFormRequestGuard;
use ReturnTag\TagCore\PublicSite\TagEntryIntent;
use ReturnTag\TagCore\PublicSite\TagEntryLinkBlock;
use ReturnTag\TagCore\Tests\Integration\Fixture\RecordingManualTagEntryRateLimiter;
use WP_Block_Type_Registry;
use WP_UnitTestCase;
use wpdb;

/**
 * Verifies the block, form, rewrite, and durable limiter adapters.
 */
final class ManualTagEntryTest extends WP_UnitTestCase {
	/**
	 * Restore isolated request and limiter state.
	 */
	protected function tearDown(): void {
		global $wpdb;

		$_POST = array();
		unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_SEC_FETCH_SITE'], $_SERVER['HTTP_ORIGIN'] );
		$this->clear_rate_limit_options( $wpdb );

		parent::tearDown();
	}

	/**
	 * The plugin registers one closed dynamic block contract.
	 */
	public function test_contract_registers_one_closed_dynamic_block(): void {
		$block = WP_Block_Type_Registry::get_instance()->get_registered( TagEntryLinkBlock::BLOCK_NAME );

		self::assertNotNull( $block );
		self::assertArrayHasKey( 'intent', $block->attributes );
		self::assertSame( array( 'activate', 'report' ), $block->attributes['intent']['enum'] );
		self::assertArrayNotHasKey( 'tag_id', $block->attributes );
		self::assertArrayNotHasKey( 'redirect', $block->attributes );
	}

	/**
	 * The block returns a same-site link and semantic dialog.
	 */
	public function test_block_renders_same_site_link_and_accessible_dialog(): void {
		$html = render_block(
			array(
				'blockName' => TagEntryLinkBlock::BLOCK_NAME,
				'attrs'     => array( 'intent' => 'report' ),
			)
		);

		self::assertStringContainsString( esc_url( home_url( '/tag/report/' ) ), $html );
		self::assertStringContainsString( 'Report a found tag', $html );
		self::assertStringContainsString( '<dialog', $html );
		self::assertStringContainsString( 'aria-labelledby=', $html );
		self::assertStringContainsString( 'data-returntag-tag-entry-form', $html );
		self::assertStringNotContainsString( '<iframe', $html );
	}

	/**
	 * Valid submission normalizes without consulting product tables.
	 */
	public function test_valid_public_submission_normalizes_without_querying_tag_state(): void {
		$handler = $this->handler();
		$this->valid_request( 'a7-r2 w9' );
		global $wpdb;
		$queries      = array();
		$record_query = static function ( string $query ) use ( &$queries ): string {
			$queries[] = $query;

			return $query;
		};
		add_filter( 'query', $record_query );

		try {
			$result = $handler->submit();
		} finally {
			remove_filter( 'query', $record_query );
		}

		foreach ( $queries as $query ) {
			self::assertStringNotContainsString( $wpdb->prefix . 'returntag_tags', $query );
			self::assertStringNotContainsString( $wpdb->prefix . 'returntag_batches', $query );
		}

		self::assertSame( ManualTagEntryFormState::READY, $result->state );
		self::assertSame( 'A7R2W9', $result->tag_id?->value );
	}

	/**
	 * Invalid raw input is never reflected in HTML.
	 */
	public function test_invalid_input_is_not_reflected_by_the_shared_renderer(): void {
		$handler = $this->handler();
		$this->valid_request( '<script>alert(1)</script>' );
		$result = $handler->submit();
		$html   = ( new ManualTagEntryTemplateRenderer( RETURNTAG_TAGCORE_DIR ) )->render_to_string(
			TagEntryIntent::ACTIVATE,
			home_url( '/tag/activate/' ),
			$result->state
		);

		self::assertSame( ManualTagEntryFormState::INVALID, $result->state );
		self::assertStringNotContainsString( '<script>', $html );
		self::assertStringContainsString( 'Enter a valid six-character Tag ID.', $html );
	}

	/**
	 * Cross-site evidence fails before capacity reservation.
	 */
	public function test_cross_site_evidence_fails_before_reserving_capacity(): void {
		$limiter = new RecordingManualTagEntryRateLimiter();
		$handler = $this->handler( $limiter );
		$this->valid_request( 'A7R2W9' );
		$_SERVER['HTTP_SEC_FETCH_SITE'] = 'cross-site';

		$result = $handler->submit();

		self::assertSame( ManualTagEntryFormState::FORBIDDEN, $result->state );
		self::assertSame( 0, $limiter->reservations );
	}

	/**
	 * Browser opaque origins are accepted only with same-site Fetch Metadata.
	 */
	public function test_opaque_origin_requires_same_site_fetch_evidence(): void {
		$handler = $this->handler();
		$this->valid_request( 'A7R2W9' );
		$_SERVER['HTTP_ORIGIN'] = 'null';

		self::assertSame( ManualTagEntryFormState::READY, $handler->submit()->state );

		unset( $_SERVER['HTTP_SEC_FETCH_SITE'] );
		self::assertSame( ManualTagEntryFormState::FORBIDDEN, $handler->submit()->state );
	}

	/**
	 * Rewrites accept only the two frozen intents.
	 */
	public function test_rewrite_is_exactly_limited_to_the_two_entry_intents(): void {
		self::assertSame( 1, preg_match( '#^' . ManualTagEntryRouteController::REWRITE_PATTERN . '#', 'tag/activate/' ) );
		self::assertSame( 1, preg_match( '#^' . ManualTagEntryRouteController::REWRITE_PATTERN . '#', 'tag/report' ) );
		self::assertSame( 0, preg_match( '#^' . ManualTagEntryRouteController::REWRITE_PATTERN . '#', 'tag/unknown/' ) );
		self::assertSame( 0, preg_match( '#^' . ManualTagEntryRouteController::REWRITE_PATTERN . '#', 'tag/report/extra' ) );
	}

	/**
	 * Durable limiter buckets contain no plaintext direct-peer address.
	 */
	public function test_durable_buckets_store_no_plain_direct_peer_address(): void {
		global $wpdb;
		$limiter = new WordPressOptionManualTagEntryRateLimiter( $wpdb, get_current_blog_id() );
		$lookup  = ( new WordPressPublicRequestHasher() )->ip_lookup( '192.0.2.44' );

		self::assertTrue(
			$limiter->reserve(
				$lookup,
				new DateTimeImmutable( '2026-08-01 00:00:00', new DateTimeZone( 'UTC' ) )
			)
		);

		$like = $wpdb->esc_like( WordPressOptionManualTagEntryRateLimiter::OPTION_PREFIX ) . '%';
		$sql  = $wpdb->prepare( 'SELECT option_name, option_value, autoload FROM %i WHERE option_name LIKE %s', $wpdb->options, $like );
		self::assertIsString( $sql );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above for isolated integration inspection.
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		self::assertCount( 4, $rows );
		foreach ( $rows as $row ) {
			self::assertStringNotContainsString( '192.0.2.44', (string) $row['option_name'] );
			self::assertStringNotContainsString( '192.0.2.44', (string) $row['option_value'] );
			self::assertNotSame( 'yes', $row['autoload'] );
		}
	}

	/**
	 * Build one form handler with a query-free fixture limiter.
	 *
	 * @param RecordingManualTagEntryRateLimiter|null $limiter Optional fixture limiter.
	 */
	private function handler( ?RecordingManualTagEntryRateLimiter $limiter = null ): ManualTagEntryFormHandler {
		$limiter ??= new RecordingManualTagEntryRateLimiter();

		return new ManualTagEntryFormHandler(
			new SubmitManualTagEntry(
				new TagIdInputNormalizer(),
				$limiter,
				new class() implements Clock {
					/**
					 * Return the fixed UTC time.
					 */
					public function now(): DateTimeImmutable {
						return new DateTimeImmutable( '2026-08-01 00:00:00', new DateTimeZone( 'UTC' ) );
					}
				}
			),
			new PublicFormRequestGuard(),
			new WordPressPublicRequestHasher()
		);
	}

	/**
	 * Populate one valid same-origin POST request.
	 *
	 * @param string $tag_id Raw public Tag ID input.
	 */
	private function valid_request( string $tag_id ): void {
		$_POST                          = array(
			ManualTagEntryFormHandler::ACTION_FIELD => ManualTagEntryFormHandler::SUBMIT_ACTION,
			ManualTagEntryFormHandler::NONCE_FIELD  => wp_create_nonce( ManualTagEntryFormHandler::NONCE_ACTION ),
			ManualTagEntryFormHandler::TAG_ID_FIELD => $tag_id,
		);
		$_SERVER['REMOTE_ADDR']         = '192.0.2.44';
		$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
	}

	/**
	 * Delete only plugin-owned limiter fixtures.
	 *
	 * @param wpdb $database Active test database.
	 */
	private function clear_rate_limit_options( wpdb $database ): void {
		$like = $database->esc_like( WordPressOptionManualTagEntryRateLimiter::OPTION_PREFIX ) . '%';
		$sql  = $database->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s', $database->options, $like );

		if ( ! is_string( $sql ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above for isolated plugin-owned fixture cleanup.
		foreach ( $database->get_col( $sql ) as $option_name ) {
			if ( is_string( $option_name ) ) {
				delete_option( $option_name );
			}
		}
	}
}
