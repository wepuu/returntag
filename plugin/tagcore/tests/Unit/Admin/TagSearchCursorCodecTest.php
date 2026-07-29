<?php
/**
 * RT-209 Tag search cursor tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Admin;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Admin\TagSearchCursorCodec;
use ReturnTag\TagCore\Application\Tag\TagSearchCriteria;
use ReturnTag\TagCore\Application\Tag\TagSearchCursor;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;

/**
 * Verifies opaque, filter-bound pagination cursors.
 */
final class TagSearchCursorCodecTest extends TestCase {
	/**
	 * A valid cursor round-trips under the same filters.
	 */
	public function test_round_trips_cursor_bound_to_filters(): void {
		$codec    = new TagSearchCursorCodec();
		$criteria = TagSearchCriteria::for_batch( 'RT-209-A', TagStatus::ACTIVE );
		$encoded  = $codec->encode( $criteria, new TagSearchCursor( TagId::from_canonical( '234567' ) ) );

		self::assertStringNotContainsString( '234567', $encoded );
		self::assertSame( '234567', $codec->decode( $criteria, $encoded )->tag_id->value );
	}

	/**
	 * A cursor cannot be replayed under another Batch filter.
	 */
	public function test_rejects_cursor_reused_with_different_filter(): void {
		$codec   = new TagSearchCursorCodec();
		$encoded = $codec->encode(
			TagSearchCriteria::for_batch( 'RT-209-A', null ),
			new TagSearchCursor( TagId::from_canonical( '234567' ) )
		);

		$this->expectException( InvalidArgumentException::class );

		$codec->decode( TagSearchCriteria::for_batch( 'RT-209-B', null ), $encoded );
	}
}
