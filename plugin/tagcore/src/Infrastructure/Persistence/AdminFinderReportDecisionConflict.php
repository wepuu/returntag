<?php
/**
 * Private transaction-conflict signal for Finder Report decisions.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use RuntimeException;

/** Forces rollback after a partial conditional-write failure. */
final class AdminFinderReportDecisionConflict extends RuntimeException {
}
