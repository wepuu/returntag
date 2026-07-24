<?php
/**
 * Stored-value mapping failure.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Exception;

/**
 * Signals a row that cannot be mapped to the accepted typed contract.
 */
final class PersistenceMappingException extends PersistenceException {
}
