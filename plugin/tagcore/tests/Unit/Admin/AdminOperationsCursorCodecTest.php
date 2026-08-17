<?php
/**
 * RT-326 operations cursor tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Admin;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Admin\AdminOperationsCursorCodec;

/** Verifies criteria binding and email exclusion. */
final class AdminOperationsCursorCodecTest extends TestCase {
	/** A cursor round-trips without containing email or plaintext position. */
	public function test_round_trips_without_exposing_exact_email(): void {
		$codec    = new AdminOperationsCursorCodec( str_repeat( 'k', 32 ) );
		$criteria = array(
			'mode'       => 'owner_id',
			'owner_id'   => 17,
			'tag_status' => 'active',
		);
		$cursor   = $codec->encode( 'tags', $criteria, '234567' );

		self::assertStringNotContainsString( 'owner@example.com', $cursor );
		self::assertStringNotContainsString( '234567', $cursor );
		self::assertSame( '234567', $codec->decode( 'tags', $criteria, $cursor ) );
	}

	/** A cursor cannot be replayed under another exact anchor. */
	public function test_rejects_cursor_under_different_filters(): void {
		$codec  = new AdminOperationsCursorCodec( str_repeat( 'k', 32 ) );
		$cursor = $codec->encode(
			'finder_reports',
			array(
				'mode'     => 'owner_id',
				'owner_id' => 17,
			),
			'44'
		);

		$this->expectException( InvalidArgumentException::class );
		$codec->decode(
			'finder_reports',
			array(
				'mode'     => 'owner_id',
				'owner_id' => 18,
			),
			$cursor
		);
	}

	/** An unavailable runtime salt fails pagination without breaking bootstrap. */
	public function test_missing_secret_fails_when_cursor_is_used(): void {
		$codec = new AdminOperationsCursorCodec( '' );

		$this->expectException( InvalidArgumentException::class );
		$codec->encode( 'tags', array( 'mode' => 'tag_id' ), '234567' );
	}
}
