<?php
/**
 * Disallowed Batch lifecycle transition.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch\Exception;

/**
 * Raised when the current Batch state forbids a lifecycle transition.
 */
final class BatchLifecycleNotAllowed extends BatchLifecycleException {
}
