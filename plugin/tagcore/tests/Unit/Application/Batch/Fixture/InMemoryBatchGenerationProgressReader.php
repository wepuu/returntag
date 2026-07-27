<?php
/**
 * In-memory Batch generation progress reader.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture;

use ReturnTag\TagCore\Application\Batch\BatchGenerationProgressReader;
use ReturnTag\TagCore\Application\Batch\BatchGenerationProgressSnapshot;

/**
 * Returns one optional immutable fixture.
 */
final readonly class InMemoryBatchGenerationProgressReader implements BatchGenerationProgressReader {
	/**
	 * Create the reader.
	 *
	 * @param BatchGenerationProgressSnapshot|null $snapshot Stored fixture.
	 */
	public function __construct( private ?BatchGenerationProgressSnapshot $snapshot ) {
	}

	/**
	 * Find progress for one Batch.
	 *
	 * @param int $batch_id Batch identifier.
	 */
	public function find( int $batch_id ): ?BatchGenerationProgressSnapshot {
		if ( null === $this->snapshot || $this->snapshot->batch_id !== $batch_id ) {
			return null;
		}

		return $this->snapshot;
	}
}
