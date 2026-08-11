<?php
/**
 * Owner Tag detail result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use ReturnTag\TagCore\Application\Persistence\Record\TagRecord;

/**
 * Carries only a closed state and an optional currently-owned Tag.
 */
final readonly class OwnerTagDetail {
	/**
	 * Create one closed detail result.
	 *
	 * @param OwnerTagAccessState $state Safe Account state.
	 * @param TagRecord|null      $tag Optional currently-owned Tag.
	 */
	public function __construct(
		public OwnerTagAccessState $state,
		public ?TagRecord $tag = null
	) {
	}
}
