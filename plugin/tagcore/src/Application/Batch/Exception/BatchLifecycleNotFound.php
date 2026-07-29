<?php
/**
 * Missing Batch lifecycle state.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch\Exception;

/**
 * Raised when the requested Batch lifecycle state does not exist.
 */
final class BatchLifecycleNotFound extends BatchLifecycleException {
}
