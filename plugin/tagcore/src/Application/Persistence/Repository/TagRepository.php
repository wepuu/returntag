<?php
/**
 * Tag persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Repository;

use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceDuplicateKeyException;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Pagination\TagCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\TagPage;
use ReturnTag\TagCore\Application\Persistence\Record\NewTagRecord;
use ReturnTag\TagCore\Application\Persistence\Record\TagRecord;

/**
 * Narrow physical Tag persistence contract.
 */
interface TagRepository {
	/**
	 * Insert one Tag after Batch-snapshot verification.
	 *
	 * @param NewTagRecord $record New Tag data.
	 * @throws PersistenceDuplicateKeyException When the Tag ID already exists.
	 */
	public function insert( NewTagRecord $record ): TagRecord;

	/**
	 * Find one Tag by public identifier.
	 *
	 * @param string $tag_id Public Tag ID.
	 */
	public function find_by_tag_id( string $tag_id ): ?TagRecord;

	/**
	 * Return one bounded Batch Tag page.
	 *
	 * @param int            $batch_id Batch identifier.
	 * @param TagCursor|null $cursor Previous cursor.
	 * @param PageSize       $page_size Bounded page size.
	 */
	public function list_by_batch( int $batch_id, ?TagCursor $cursor, PageSize $page_size ): TagPage;

	/**
	 * Return one bounded Owner Tag page.
	 *
	 * @param int            $owner_id WordPress User ID.
	 * @param TagCursor|null $cursor Previous cursor.
	 * @param PageSize       $page_size Bounded page size.
	 */
	public function list_by_owner( int $owner_id, ?TagCursor $cursor, PageSize $page_size ): TagPage;
}
