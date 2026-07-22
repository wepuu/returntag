<?php
/**
 * Invalid migration registry exception.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use LogicException;

/**
 * Raised when numbered migrations do not form one ordered contiguous sequence.
 */
final class MigrationRegistryException extends LogicException {
}
