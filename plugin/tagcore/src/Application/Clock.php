<?php
/**
 * Application clock contract.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application;

use DateTimeImmutable;

/**
 * Supplies the current UTC time to application use cases.
 */
interface Clock {
	/**
	 * Return the current time in UTC.
	 */
	public function now(): DateTimeImmutable;
}
