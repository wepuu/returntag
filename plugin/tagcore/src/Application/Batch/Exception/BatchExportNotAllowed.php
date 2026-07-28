<?php
/**
 * Batch export state exception.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch\Exception;

/**
 * Raised when the current Batch state forbids manufacturing export.
 */
final class BatchExportNotAllowed extends BatchExportException {
}
