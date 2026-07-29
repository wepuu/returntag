<?php
/**
 * RT-209 Tag search application tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Tag;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Tag\SearchTags;
use ReturnTag\TagCore\Application\Tag\TagSearchCriteria;
use ReturnTag\TagCore\Application\Tag\TagSearchCursor;
use ReturnTag\TagCore\Application\Tag\TagSearchPage;
use ReturnTag\TagCore\Application\Tag\TagSearchReader;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Verifies application-level pagination mode rules.
 */
final class SearchTagsTest extends TestCase {
	/**
	 * Batch searches delegate their bounded query.
	 */
	public function test_delegates_bounded_batch_search(): void {
		$page   = new TagSearchPage( array(), null );
		$reader = new class( $page ) implements TagSearchReader {
			/**
			 * Number of reader calls.
			 *
			 * @var int
			 */
			public int $calls = 0;

			/**
			 * Create the fixture.
			 *
			 * @param TagSearchPage $page Fixed result page.
			 */
			public function __construct( private readonly TagSearchPage $page ) {
			}

			/**
			 * Return the fixed page.
			 *
			 * @param TagSearchCriteria    $criteria Search criteria.
			 * @param TagSearchCursor|null $cursor Cursor.
			 * @param PageSize             $page_size Page size.
			 */
			public function search(
				TagSearchCriteria $criteria,
				?TagSearchCursor $cursor,
				PageSize $page_size
			): TagSearchPage {
				unset( $criteria, $cursor, $page_size );
				++$this->calls;
				return $this->page;
			}
		};

		self::assertSame(
			$page,
			( new SearchTags( $reader ) )->execute(
				TagSearchCriteria::for_batch( 'RT-209', null ),
				null,
				new PageSize()
			)
		);
		self::assertSame( 1, $reader->calls );
	}

	/**
	 * Exact Tag ID searches cannot accept pagination cursors.
	 */
	public function test_exact_tag_id_search_rejects_cursor(): void {
		$reader = new class() implements TagSearchReader {
			/**
			 * Return an empty page.
			 *
			 * @param TagSearchCriteria    $criteria Search criteria.
			 * @param TagSearchCursor|null $cursor Cursor.
			 * @param PageSize             $page_size Page size.
			 */
			public function search(
				TagSearchCriteria $criteria,
				?TagSearchCursor $cursor,
				PageSize $page_size
			): TagSearchPage {
				unset( $criteria, $cursor, $page_size );
				return new TagSearchPage( array(), null );
			}
		};

		$this->expectException( InvalidArgumentException::class );

		( new SearchTags( $reader ) )->execute(
			TagSearchCriteria::for_tag_id( TagId::from_canonical( '234567' ) ),
			new TagSearchCursor( TagId::from_canonical( '234568' ) ),
			new PageSize()
		);
	}
}
