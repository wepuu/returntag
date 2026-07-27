<?php
/**
 * Duplicate persistence key failure.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Exception;

/**
 * Signals an insert rejected by a database unique-key constraint.
 */
final class PersistenceDuplicateKeyException extends PersistenceException {
}
