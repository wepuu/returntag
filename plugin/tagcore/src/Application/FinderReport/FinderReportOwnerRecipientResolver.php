<?php
/**
 * Current Finder Report Owner recipient port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use ReturnTag\TagCore\Domain\Tag\TagId;

/** Resolves the current Owner at notification time. */
interface FinderReportOwnerRecipientResolver {
	/**
	 * Resolve the current Owner without trusting the submission snapshot.
	 *
	 * @param TagId $tag_id Server-resolved Tag.
	 */
	public function resolve( TagId $tag_id ): ?FinderReportOwnerRecipient;
}
