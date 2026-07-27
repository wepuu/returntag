<?php
/**
 * Batch generation integrity failure.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch\Exception;

/**
 * Signals inconsistent Batch progress or Tag storage.
 */
final class BatchGenerationIntegrityViolation extends BatchGenerationException {
}
