<?php
/**
 * Batch Tag status counts.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * Privacy-safe aggregate counts; no Tag identifiers are exposed.
 */
final readonly class BatchTagCounts {
	/**
	 * Total generated Tags across canonical statuses.
	 *
	 * @var int
	 */
	public int $total;

	/**
	 * Create aggregate status counts.
	 *
	 * @param int $unregistered Unregistered Tag count.
	 * @param int $active Active Tag count.
	 * @param int $suspended Suspended Tag count.
	 * @param int $retired Retired Tag count.
	 */
	public function __construct(
		public int $unregistered,
		public int $active,
		public int $suspended,
		public int $retired
	) {
		RecordValidator::unsigned_int( $this->unregistered, 'unregistered' );
		RecordValidator::unsigned_int( $this->active, 'active' );
		RecordValidator::unsigned_int( $this->suspended, 'suspended' );
		RecordValidator::unsigned_int( $this->retired, 'retired' );
		$this->total = $this->unregistered + $this->active + $this->suspended + $this->retired;
	}
}
