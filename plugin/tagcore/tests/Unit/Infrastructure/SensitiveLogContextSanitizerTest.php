<?php
/**
 * Unit coverage for defensive log sanitization.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Infrastructure\SensitiveLogContextSanitizer;
use RuntimeException;

/**
 * Verifies that operational logs cannot directly expose sensitive values.
 */
final class SensitiveLogContextSanitizerTest extends TestCase {
	/**
	 * Sensitive keys must be redacted recursively while safe metadata remains.
	 */
	public function test_sensitive_keys_are_redacted_recursively(): void {
		$sanitizer = new SensitiveLogContextSanitizer();
		$context   = array(
			'tag_id'       => 'ABC234',
			'owner_email'  => 'owner@example.test',
			'nested'       => array(
				'access_token' => 'plain-test-token',
				'attempts'     => 2,
			),
			'message_body' => 'Private test message.',
		);

		self::assertSame(
			array(
				'tag_id'       => 'ABC234',
				'owner_email'  => '[redacted-email]',
				'nested'       => array(
					'access_token' => '[redacted]',
					'attempts'     => 2,
				),
				'message_body' => '[redacted]',
			),
			$sanitizer->sanitize_context( $context )
		);
	}

	/**
	 * Common credentials and addresses must be removed from free-form messages.
	 */
	public function test_message_patterns_are_redacted(): void {
		$sanitizer = new SensitiveLogContextSanitizer();

		self::assertSame(
			'Notify [redacted-email] using Bearer [redacted] and token=[redacted]',
			$sanitizer->sanitize_message(
				'Notify owner@example.test using Bearer abc.def and token=plain-test-token'
			)
		);
	}

	/**
	 * Exceptions must expose only stable diagnostic metadata.
	 */
	public function test_exception_message_and_trace_are_not_logged(): void {
		$sanitizer = new SensitiveLogContextSanitizer();
		$exception = new RuntimeException( 'access_token=plain-test-token', 42 );

		self::assertSame(
			array(
				'exception' => array(
					'exception_class' => RuntimeException::class,
					'exception_code'  => 42,
				),
			),
			$sanitizer->sanitize_context( array( 'exception' => $exception ) )
		);
	}

	/**
	 * Untrusted strings and nested context must remain bounded.
	 */
	public function test_strings_and_nested_context_are_bounded(): void {
		$sanitizer = new SensitiveLogContextSanitizer();
		$context   = array(
			'note'  => str_repeat( 'a', 2048 ),
			'first' => array(
				'second' => array(
					'third' => array(
						'fourth' => array(
							'fifth' => array(
								'sixth' => array( 'safe' => 'value' ),
							),
						),
					),
				),
			),
		);
		$result    = $sanitizer->sanitize_context( $context );

		self::assertIsString( $result['note'] );
		self::assertSame( 1024, strlen( $result['note'] ) );
		self::assertStringEndsWith( '[truncated]', $result['note'] );
		self::assertSame( '[max-depth]', $result['first']['second']['third']['fourth']['fifth']['sixth'] );
	}

	/**
	 * Each context array must have a bounded number of entries.
	 */
	public function test_context_item_count_is_bounded(): void {
		$sanitizer = new SensitiveLogContextSanitizer();
		$context   = array();

		for ( $index = 0; $index < 55; ++$index ) {
			$context[ 'safe_' . $index ] = $index;
		}

		$result = $sanitizer->sanitize_context( $context );

		self::assertCount( 51, $result );
		self::assertTrue( $result['__truncated__'] );
		self::assertArrayNotHasKey( 'safe_50', $result );
	}

	/**
	 * A malformed key must not leak an address or credential embedded in it.
	 */
	public function test_sensitive_values_embedded_in_keys_are_removed(): void {
		$sanitizer = new SensitiveLogContextSanitizer();

		$result = $sanitizer->sanitize_context(
			array(
				'owner@example.test'     => 'email-key',
				'token=plain-test-token' => 'token-key',
				'access_token'           => 'plain-test-token',
			)
		);

		self::assertSame( 'email-key', $result['redacted_key_0'] );
		self::assertSame( '[redacted]', $result['redacted_key_1'] );
		self::assertSame( '[redacted]', $result['access_token'] );
		self::assertArrayNotHasKey( 'owner@example.test', $result );
		self::assertArrayNotHasKey( 'token=plain-test-token', $result );
	}
}
