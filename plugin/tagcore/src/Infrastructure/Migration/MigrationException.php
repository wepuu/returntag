<?php
/**
 * Migration execution exception.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use RuntimeException;

/**
 * Represents a safe-to-surface migration category without raw database detail.
 */
final class MigrationException extends RuntimeException {
}
