<?php
/**
 * Exhausted Tag ID collision retries.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag\Exception;

use RuntimeException;

/**
 * Signals that no candidate could be inserted within the fixed retry bound.
 */
final class TagIdCollisionRetryExhausted extends RuntimeException {
}
