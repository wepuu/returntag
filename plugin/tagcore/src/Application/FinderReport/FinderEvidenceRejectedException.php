<?php
/**
 * Finder evidence safety rejection.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use RuntimeException;

/**
 * Fixed privacy-safe failure when an approved reviewer rejects evidence.
 */
final class FinderEvidenceRejectedException extends RuntimeException {
}
