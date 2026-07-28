<?php
/**
 * Missing Batch inventory exception.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch\Exception;

use RuntimeException;

/**
 * Raised when the requested Batch does not exist.
 */
final class BatchTagInventoryNotFound extends RuntimeException {
}
