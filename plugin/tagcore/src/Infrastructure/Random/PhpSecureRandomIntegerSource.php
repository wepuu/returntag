<?php
/**
 * PHP cryptographically secure random integer source.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Random;

use ReturnTag\TagCore\Application\Tag\RandomIntegerSource;

/**
 * Uses PHP's operating-system-backed cryptographically secure random source.
 */
final class PhpSecureRandomIntegerSource implements RandomIntegerSource {
	/**
	 * Return an integer between the inclusive bounds.
	 *
	 * @param int $minimum Inclusive minimum.
	 * @param int $maximum Inclusive maximum.
	 */
	public function between( int $minimum, int $maximum ): int {
		return \random_int( $minimum, $maximum );
	}
}
