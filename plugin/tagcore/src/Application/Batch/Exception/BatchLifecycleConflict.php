<?php
/**
 * Stale Batch lifecycle request.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch\Exception;

/**
 * Raised when a concurrent lifecycle change invalidates the request.
 */
final class BatchLifecycleConflict extends BatchLifecycleException {
}
