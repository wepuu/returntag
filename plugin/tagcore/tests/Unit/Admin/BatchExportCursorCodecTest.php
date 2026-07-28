<?php
/**
 * RT-207 Batch export cursor tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Admin;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Admin\BatchExportCursorCodec;
use ReturnTag\TagCore\Application\Persistence\Pagination\BatchExportCursor;

/**
 * Verifies strict versioned export-history cursors.
 */
final class BatchExportCursorCodecTest extends TestCase {
	/**
	 * Cursor round trips without exposing a plain version.
	 */
	public function test_round_trips_one_export_cursor(): void {
		$codec   = new BatchExportCursorCodec();
		$encoded = $codec->encode( new BatchExportCursor( 27 ) );

		self::assertNotSame( '27', $encoded );
		self::assertSame( 27, $codec->decode( $encoded )->export_version );
	}

	/**
	 * Malformed and unknown cursors fail closed.
	 *
	 * @param string $cursor Invalid cursor.
	 * @dataProvider invalid_cursors
	 */
	public function test_rejects_invalid_cursors( string $cursor ): void {
		$this->expectException( InvalidArgumentException::class );

		( new BatchExportCursorCodec() )->decode( $cursor );
	}

	/**
	 * Invalid cursor provider.
	 *
	 * @return array<string, array{string}>
	 */
	public function invalid_cursors(): array {
		return array(
			'empty'       => array( '' ),
			'punctuation' => array( '***' ),
			'wrong type'  => array( 'cnQyMDY6MQ' ),
			'zero'        => array( 'cnQyMDc6MA' ),
			'padding'     => array( 'cnQyMDc6MQ==' ),
		);
	}
}
