<?php
/**
 * Finder evidence private-object purpose.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\FinderReport;

/**
 * Separates source, review, and email objects cryptographically and physically.
 */
enum PrivateMediaObjectKind: string {
	case SOURCE = 'source';
	case REVIEW = 'review';
	case EMAIL  = 'email';
}
