<?php
/**
 * Approved Finder evidence MIME values.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\FinderReport;

/**
 * Closed set accepted by the future evidence boundary.
 */
enum FinderEvidenceMime: string {
	case JPEG = 'image/jpeg';
	case PNG  = 'image/png';
	case WEBP = 'image/webp';
}
