<?php
/**
 * Missing Batch export exception.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch\Exception;

/**
 * Raised when the requested Batch does not exist.
 */
final class BatchExportNotFound extends BatchExportException {
}
