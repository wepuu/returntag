<?php
/**
 * Tag entry same-site URL provider.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Generates same-site URLs without assuming the WordPress installation path.
 */
final class TagEntryUrlProvider {
	/**
	 * Return one stable manual-entry URL.
	 *
	 * @param TagEntryIntent $intent Closed presentation intent.
	 */
	public function entry_url( TagEntryIntent $intent ): string {
		return home_url( '/tag/' . $intent->value . '/' );
	}

	/**
	 * Return the authoritative public Tag URL.
	 *
	 * @param TagId $tag_id Canonical Tag ID.
	 */
	public function canonical_tag_url( TagId $tag_id ): string {
		return home_url( '/t/' . rawurlencode( $tag_id->value ) );
	}
}
