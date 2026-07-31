<?php
/**
 * WordPress authenticated session integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Infrastructure\Auth\WordPressAuthenticatedSession;
use ReturnTag\TagCore\Tests\Integration\Fixture\RecordingAuthenticationCookieEmitter;
use RuntimeException;
use WP_Session_Tokens;
use WP_UnitTestCase;

/**
 * Verifies native WordPress identity and token creation.
 */
final class WordPressAuthenticatedSessionTest extends WP_UnitTestCase {
	/**
	 * Reset the in-process identity after each test.
	 */
	protected function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Authentication establishes a fresh non-persistent WordPress session.
	 */
	public function test_authenticate_sets_current_user_and_native_session_token(): void {
		$user_id  = self::factory()->user->create(
			array(
				'user_email' => 'session-owner@example.test',
				'role'       => 'subscriber',
			)
		);
		$sessions = WP_Session_Tokens::get_instance( $user_id );
		$sessions->destroy_all();

		$emitter = new RecordingAuthenticationCookieEmitter();
		$adapter = new WordPressAuthenticatedSession( $emitter );
		$adapter->authenticate( $user_id );

		self::assertSame( $user_id, get_current_user_id() );
		self::assertSame( $user_id, $adapter->current_user_id() );
		self::assertCount( 1, $sessions->get_all() );
		self::assertSame( 1, $emitter->clear_count );
		self::assertCount(
			constant( 'COOKIEPATH' ) === constant( 'SITECOOKIEPATH' ) ? 3 : 4,
			$emitter->writes
		);

		foreach ( $emitter->writes as $write ) {
			$options = $write['options']->to_native_options();

			self::assertTrue( $options['httponly'] );
			self::assertSame( 'Lax', $options['samesite'] );
		}
	}

	/**
	 * An invalid identity cannot mint a session.
	 */
	public function test_invalid_user_is_rejected_before_session_creation(): void {
		$adapter = new WordPressAuthenticatedSession( new RecordingAuthenticationCookieEmitter() );

		$this->expectException( RuntimeException::class );
		$adapter->authenticate( PHP_INT_MAX );
	}

	/**
	 * A browser cookie failure revokes the otherwise orphaned native token.
	 */
	public function test_cookie_failure_revokes_new_session_token(): void {
		$user_id  = self::factory()->user->create(
			array(
				'user_email' => 'failed-session@example.test',
				'role'       => 'subscriber',
			)
		);
		$sessions = WP_Session_Tokens::get_instance( $user_id );
		$sessions->destroy_all();
		$emitter = new RecordingAuthenticationCookieEmitter( false );
		$adapter = new WordPressAuthenticatedSession( $emitter );

		try {
			$adapter->authenticate( $user_id );
			self::fail( 'Cookie failure must reject authentication.' );
		} catch ( RuntimeException ) {
			self::assertSame( 2, $emitter->clear_count );
			self::assertCount( 0, $sessions->get_all() );
			self::assertSame( 0, get_current_user_id() );
		}
	}
}
