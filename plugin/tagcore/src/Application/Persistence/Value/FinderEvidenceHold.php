<?php
/**
 * Current Finder evidence hold projection.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Value;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/** Complete current Hold tuple without evidence metadata. */
final readonly class FinderEvidenceHold {
	/**
	 * Create one complete Hold.
	 *
	 * @param DateTimeImmutable $until Hold boundary.
	 * @param DateTimeImmutable $placed_at Placement instant.
	 * @param int               $placed_by Operator User ID.
	 */
	public function __construct( public DateTimeImmutable $until, public DateTimeImmutable $placed_at, public int $placed_by ) {
		RecordValidator::utc( $until, 'hold_until' );
		RecordValidator::utc( $placed_at, 'hold_placed_at' );
		RecordValidator::positive_id( $placed_by, 'hold_placed_by' );
	}
	/**
	 * Determine whether this Hold is active.
	 *
	 * @param DateTimeImmutable $now Current UTC instant.
	 */
	public function active( DateTimeImmutable $now ): bool {
		return $this->until > $now;
	}
}
