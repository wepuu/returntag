<?php
/**
 * Get Batch application service.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Application\Persistence\Record\BatchRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\BatchRepository;

/**
 * Resolves one Batch by its internal identifier.
 */
final readonly class GetBatch {
	/**
	 * Create the query service.
	 *
	 * @param BatchRepository $batches Batch persistence.
	 */
	public function __construct( private BatchRepository $batches ) {
	}

	/**
	 * Return one Batch or null.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function execute( int $batch_id ): ?BatchRecord {
		return $this->batches->find_by_id( $batch_id );
	}
}
