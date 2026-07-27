<?php
/**
 * In-memory Tag Repository fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Tag\Fixture;

use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceException;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Pagination\TagCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\TagPage;
use ReturnTag\TagCore\Application\Persistence\Record\NewTagRecord;
use ReturnTag\TagCore\Application\Persistence\Record\TagRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\TagRepository;

/**
 * Records insert attempts and exposes deterministic failures.
 */
final class InMemoryTagRepository implements TagRepository {
	/**
	 * Attempted records in call order.
	 *
	 * @var list<NewTagRecord>
	 */
	public array $attempts = array();

	/**
	 * Successfully stored records indexed by Tag ID.
	 *
	 * @var array<string, TagRecord>
	 */
	public array $records = array();

	/**
	 * Create the fixture.
	 *
	 * @param array<string, PersistenceException> $failures Failures indexed by Tag ID.
	 */
	public function __construct( private readonly array $failures = array() ) {
	}

	/**
	 * Insert one Tag or throw its configured failure.
	 *
	 * @param NewTagRecord $record New Tag data.
	 * @throws PersistenceException When a failure is configured for the Tag ID.
	 */
	public function insert( NewTagRecord $record ): TagRecord {
		$this->attempts[] = $record;

		if ( isset( $this->failures[ $record->tag_id ] ) ) {
			throw $this->failures[ $record->tag_id ];
		}

		$stored = new TagRecord( $record );

		$this->records[ $record->tag_id ] = $stored;

		return $stored;
	}

	/**
	 * Find one stored Tag.
	 *
	 * @param string $tag_id Public Tag ID.
	 */
	public function find_by_tag_id( string $tag_id ): ?TagRecord {
		return $this->records[ $tag_id ] ?? null;
	}

	/**
	 * Return an empty page; list behavior is outside this fixture's scope.
	 *
	 * @param int            $batch_id Batch identifier.
	 * @param TagCursor|null $cursor Previous cursor.
	 * @param PageSize       $page_size Bounded page size.
	 */
	public function list_by_batch( int $batch_id, ?TagCursor $cursor, PageSize $page_size ): TagPage {
		unset( $batch_id, $cursor, $page_size );

		return new TagPage( array(), null );
	}

	/**
	 * Return an empty page; list behavior is outside this fixture's scope.
	 *
	 * @param int            $owner_id WordPress owner User ID.
	 * @param TagCursor|null $cursor Previous cursor.
	 * @param PageSize       $page_size Bounded page size.
	 */
	public function list_by_owner( int $owner_id, ?TagCursor $cursor, PageSize $page_size ): TagPage {
		unset( $owner_id, $cursor, $page_size );

		return new TagPage( array(), null );
	}
}
