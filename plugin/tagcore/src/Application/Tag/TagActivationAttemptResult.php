<?php
/**
 * Authenticated Tag activation-attempt result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use ReturnTag\TagCore\Application\PublicTag\PublicTagPage;

/**
 * Carries only committed public state plus generic attempt feedback.
 */
final readonly class TagActivationAttemptResult {
	/**
	 * Create the result.
	 *
	 * @param TagActivationAttemptStatus $status Generic attempt status.
	 * @param PublicTagPage              $page Committed privacy-safe page.
	 */
	public function __construct(
		public TagActivationAttemptStatus $status,
		public PublicTagPage $page
	) {
	}
}
