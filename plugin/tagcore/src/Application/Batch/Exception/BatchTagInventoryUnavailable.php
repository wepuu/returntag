<?php
/**
 * Unavailable Batch inventory exception.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch\Exception;

use RuntimeException;

/**
 * Raised until a Batch owns its complete generated inventory.
 */
final class BatchTagInventoryUnavailable extends RuntimeException {
}
