<?php
/**
 * WordPress integration coverage for structured operational logging.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use Psr\Log\InvalidArgumentException;
use ReturnTag\TagCore\Infrastructure\SensitiveLogContextSanitizer;
use ReturnTag\TagCore\Infrastructure\WordPressErrorLogLogger;
use WP_UnitTestCase;

/**
 * Verifies WordPress JSON encoding and explicit emission controls.
 */
final class WordPressErrorLogLoggerTest extends WP_UnitTestCase {
	/**
	 * Enabled logging emits one sanitized, structured record.
	 */
	public function test_enabled_logger_emits_sanitized_json_record(): void {
		$lines  = array();
		$writer = static function ( string $line ) use ( &$lines ): void {
			$lines[] = $line;
		};
		$logger = new WordPressErrorLogLogger(
			new SensitiveLogContextSanitizer(),
			true,
			$writer
		);

		$logger->info(
			'Notify owner@example.test',
			array(
				'tag_id'       => 'ABC234',
				'access_token' => 'plain-test-token',
			)
		);

		self::assertCount( 1, $lines );
		self::assertStringStartsWith( '[TagCore] ', $lines[0] );
		self::assertStringNotContainsString( 'owner@example.test', $lines[0] );
		self::assertStringNotContainsString( 'plain-test-token', $lines[0] );

		$payload = json_decode( substr( $lines[0], 10 ), true, 512, JSON_THROW_ON_ERROR );

		self::assertSame( 'tagcore', $payload['channel'] );
		self::assertSame( 'info', $payload['level'] );
		self::assertSame( 'Notify [redacted-email]', $payload['message'] );
		self::assertSame( 'ABC234', $payload['context']['tag_id'] );
		self::assertSame( '[redacted]', $payload['context']['access_token'] );
	}

	/**
	 * Logging must remain inert unless a caller explicitly enables emission.
	 */
	public function test_disabled_logger_does_not_emit(): void {
		$lines  = array();
		$writer = static function ( string $line ) use ( &$lines ): void {
			$lines[] = $line;
		};
		$logger = new WordPressErrorLogLogger(
			new SensitiveLogContextSanitizer(),
			false,
			$writer
		);

		$logger->error( 'No output expected.' );

		self::assertSame( array(), $lines );
	}

	/**
	 * Invalid levels must fail consistently even while emission is disabled.
	 */
	public function test_invalid_level_is_rejected_when_disabled(): void {
		$logger = new WordPressErrorLogLogger( new SensitiveLogContextSanitizer() );

		$this->expectException( InvalidArgumentException::class );

		$logger->log( 'verbose', 'Invalid level.' );
	}
}
