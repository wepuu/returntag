<?php
/**
 * Disallowed Batch generation state.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch\Exception;

/**
 * Signals that the Batch lifecycle does not permit generation.
 */
final class BatchGenerationNotAllowed extends BatchGenerationException {
}
