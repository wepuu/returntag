<?php
/**
 * Duplicate Batch Code application failure.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch\Exception;

use RuntimeException;

/**
 * Indicates that a requested Batch Code is already persisted.
 */
final class BatchCodeAlreadyExists extends RuntimeException {
}
