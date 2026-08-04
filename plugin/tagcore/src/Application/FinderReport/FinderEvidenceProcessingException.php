<?php
/**
 * Finder evidence processing failure.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use RuntimeException;

/**
 * Fixed privacy-safe failure for malformed or unsupported image bytes.
 */
final class FinderEvidenceProcessingException extends RuntimeException {
}
