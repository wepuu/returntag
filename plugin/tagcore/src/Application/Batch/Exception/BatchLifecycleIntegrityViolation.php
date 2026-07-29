<?php
/**
 * Inconsistent Batch lifecycle storage.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch\Exception;

/**
 * Raised when persisted manufacturing evidence is inconsistent.
 */
final class BatchLifecycleIntegrityViolation extends BatchLifecycleException {
}
