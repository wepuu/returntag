<?php
/**
 * Safe persistence failure.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Exception;

use RuntimeException;

/**
 * Base exception that must not expose SQL or stored values.
 */
class PersistenceException extends RuntimeException {
}
