<?php
/**
 * RT-206 Batch Tag inventory cursor tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Admin;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Admin\BatchTagInventoryCursorCodec;
use ReturnTag\TagCore\Application\Batch\BatchTagInventoryCursor;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Verifies versioned, opaque, and strict cursor handling.
 */
final class BatchTagInventoryCursorCodecTest extends TestCase {
	/**
	 * A valid cursor round-trips without exposing a raw Tag ID.
	 */
	public function test_round_trips_versioned_cursor(): void {
		$codec   = new BatchTagInventoryCursorCodec();
		$encoded = $codec->encode(
			new BatchTagInventoryCursor( TagId::from_canonical( '234567' ) )
		);

		self::assertStringNotContainsString( '234567', $encoded );
		self::assertSame( '234567', $codec->decode( $encoded )->tag_id->value );
	}

	/**
	 * Malformed, unknown-version, and non-canonical cursors are rejected.
	 *
	 * @param string $cursor External cursor.
	 * @dataProvider invalid_cursors
	 */
	public function test_rejects_invalid_cursor( string $cursor ): void {
		$this->expectException( InvalidArgumentException::class );

		( new BatchTagInventoryCursorCodec() )->decode( $cursor );
	}

	/**
	 * Invalid cursor provider.
	 *
	 * @return array<string, array{string}>
	 */
	public function invalid_cursors(): array {
		return array(
			'empty'           => array( '' ),
			'unsafe alphabet' => array( '***' ),
			'unknown version' => array( $this->base64url( 'rt207:234567' ) ),
			'invalid Tag ID'  => array( $this->base64url( 'rt206:ABC10O' ) ),
			'extra bytes'     => array( $this->base64url( 'rt206:234567X' ) ),
		);
	}

	/**
	 * Encode one test payload.
	 *
	 * @param string $value Raw value.
	 */
	private function base64url( string $value ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Builds benign invalid cursor fixtures.
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}
}
