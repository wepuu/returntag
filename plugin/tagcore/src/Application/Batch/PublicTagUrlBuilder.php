<?php
/**
 * Public Tag URL builder port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Builds the trusted QR destination for one public Tag ID.
 */
interface PublicTagUrlBuilder {
	/**
	 * Return one absolute public Tag URL.
	 *
	 * @param TagId $tag_id Public Tag ID.
	 */
	public function for_tag( TagId $tag_id ): string;
}
