<?php
/**
 * Batch generation application failure.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch\Exception;

use RuntimeException;

/**
 * Base type for privacy-safe Batch generation failures.
 */
class BatchGenerationException extends RuntimeException {
}
