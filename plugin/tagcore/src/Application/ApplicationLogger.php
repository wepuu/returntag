<?php
/**
 * Application logging port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application;

use Psr\Log\LoggerInterface;

/**
 * Marks the PSR-3 logger used by application services.
 */
interface ApplicationLogger extends LoggerInterface {
}
