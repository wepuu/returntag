<?php
/**
 * Owner Tag collection result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use ReturnTag\TagCore\Application\Persistence\Pagination\TagPage;

/**
 * Carries only a closed state and an optional bounded page.
 */
final readonly class OwnerTagCollection {
	/**
	 * Create one closed collection result.
	 *
	 * @param OwnerTagAccessState $state Safe Account state.
	 * @param TagPage|null        $page Optional bounded Owner Tag page.
	 */
	public function __construct(
		public OwnerTagAccessState $state,
		public ?TagPage $page = null
	) {
	}
}
