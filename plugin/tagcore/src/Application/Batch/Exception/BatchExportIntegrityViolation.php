<?php
/**
 * Batch export integrity exception.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch\Exception;

/**
 * Raised when immutable export inputs or audit history have drifted.
 */
final class BatchExportIntegrityViolation extends BatchExportException {
}
