<?php
/**
 * Privacy-safe Finder Report submission failure.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use RuntimeException;

/** Exposes no Tag, upload, storage, or database details. */
final class FinderReportSubmissionException extends RuntimeException {
}
