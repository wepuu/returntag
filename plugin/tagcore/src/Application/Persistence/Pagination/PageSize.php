<?php
/**
 * Bounded Repository page size.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Pagination;

use InvalidArgumentException;

/**
 * Prevents unbounded Repository queries.
 */
final readonly class PageSize {
	public const DEFAULT = 50;
	public const MAXIMUM = 100;

	/**
	 * Create a bounded page size.
	 *
	 * @param int $value Requested page size.
	 * @throws InvalidArgumentException When the value is outside the accepted range.
	 */
	public function __construct( public int $value = self::DEFAULT ) {
		if ( $this->value < 1 || $this->value > self::MAXIMUM ) {
			throw new InvalidArgumentException( 'Page size is outside the accepted range.' );
		}
	}
}
