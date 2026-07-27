<?php
/**
 * Random integer source contract.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

/**
 * Supplies uniformly distributed integers within inclusive bounds.
 */
interface RandomIntegerSource {
	/**
	 * Return an integer between the inclusive bounds.
	 *
	 * @param int $minimum Inclusive minimum.
	 * @param int $maximum Inclusive maximum.
	 */
	public function between( int $minimum, int $maximum ): int;
}
