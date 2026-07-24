<?php
/**
 * Persistence integrity failure.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Exception;

/**
 * Signals a uniqueness, reference, or record-integrity violation.
 */
final class PersistenceConstraintViolationException extends PersistenceException {
}
