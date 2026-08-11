<?php
/**
 * Current-Owner Tag read port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Pagination\TagCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\TagPage;
use ReturnTag\TagCore\Application\Persistence\Record\TagRecord;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Reads Tags only through an explicit current-Owner predicate.
 */
interface OwnerTagReader {
	/**
	 * Return one bounded page for the current Owner.
	 *
	 * @param int            $owner_id Current WordPress User ID.
	 * @param TagCursor|null $cursor Optional stable pagination cursor.
	 * @param PageSize       $page_size Bounded page size.
	 */
	public function list_for_owner( int $owner_id, ?TagCursor $cursor, PageSize $page_size ): TagPage;

	/**
	 * Return one Tag only when it still belongs to the current Owner.
	 *
	 * @param int   $owner_id Current WordPress User ID.
	 * @param TagId $tag_id Canonical public Tag ID.
	 */
	public function find_for_owner( int $owner_id, TagId $tag_id ): ?TagRecord;
}
