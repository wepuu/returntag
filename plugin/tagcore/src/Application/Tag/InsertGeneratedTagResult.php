<?php
/**
 * Generated Tag insertion result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Record\TagRecord;

/**
 * Returns the persisted Tag and aggregate collision count only.
 */
final readonly class InsertGeneratedTagResult {
	/**
	 * Create the result.
	 *
	 * @param TagRecord $tag Persisted Tag.
	 * @param int       $collision_count Number of rejected candidates.
	 * @throws InvalidArgumentException When the collision count is negative.
	 */
	public function __construct(
		public TagRecord $tag,
		public int $collision_count
	) {
		if ( $this->collision_count < 0 ) {
			throw new InvalidArgumentException( 'Collision count cannot be negative.' );
		}
	}
}
