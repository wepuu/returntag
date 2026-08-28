<?php
/**
 * Privacy request runtime unavailable error.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Privacy\Exception;

use RuntimeException;

/** Fails closed while the independent runtime control is disabled. */
final class PrivacyRequestUnavailable extends RuntimeException {}
