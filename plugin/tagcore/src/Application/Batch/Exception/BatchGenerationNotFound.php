<?php
/**
 * Missing Batch generation target.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch\Exception;

/**
 * Signals that the requested Batch does not exist.
 */
final class BatchGenerationNotFound extends BatchGenerationException {
}
