<?php
/**
 * Finder evidence safety-provider failure.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use RuntimeException;

/**
 * Fails closed when no approved content-safety decision can be produced.
 */
final class FinderEvidenceSafetyUnavailableException extends RuntimeException {
}
