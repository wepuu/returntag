<?php
/**
 * Privacy request committed-state conflict.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Privacy\Exception;

use RuntimeException;

/** Reports a stale or illegal transition without exposing stored state. */
final class PrivacyRequestConflict extends RuntimeException {}
