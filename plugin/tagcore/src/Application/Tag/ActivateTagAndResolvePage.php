<?php
/**
 * Activation state-convergence use case.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use ReturnTag\TagCore\Application\PublicTag\PublicTagPage;
use ReturnTag\TagCore\Application\PublicTag\ResolvePublicTagPage;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Converts every activation persistence outcome into committed public state.
 */
final readonly class ActivateTagAndResolvePage {
	/**
	 * Create the state-convergence use case.
	 *
	 * @param ActivateTag          $activation Atomic activation use case.
	 * @param ResolvePublicTagPage $pages Committed public state resolver.
	 */
	public function __construct(
		private ActivateTag $activation,
		private ResolvePublicTagPage $pages
	) {
	}

	/**
	 * Attempt activation, then resolve only the committed product state.
	 *
	 * @param TagId $tag_id Canonical public Tag ID.
	 * @param int   $owner_id Server-derived WordPress User ID.
	 */
	public function execute( TagId $tag_id, int $owner_id ): PublicTagPage {
		$this->activation->execute( $tag_id, $owner_id );

		return $this->pages->execute( $tag_id, $owner_id );
	}
}
