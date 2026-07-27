<?php
/**
 * Batch generation queue failure.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch\Exception;

/**
 * Signals that background work could not be scheduled.
 */
final class BatchGenerationQueueUnavailable extends BatchGenerationException {
}
