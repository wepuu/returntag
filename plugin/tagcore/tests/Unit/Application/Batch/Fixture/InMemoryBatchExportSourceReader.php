<?php
/**
 * In-memory Batch export source fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture;

use ReturnTag\TagCore\Application\Batch\BatchExportSourceReader;
use ReturnTag\TagCore\Application\Batch\BatchExportSourceTag;

/**
 * Returns a fixed deterministic source.
 */
final readonly class InMemoryBatchExportSourceReader implements BatchExportSourceReader {
	/**
	 * Create the reader.
	 *
	 * @param array $tags Fixed Tags.
	 * @phpstan-param list<BatchExportSourceTag> $tags
	 */
	public function __construct( private array $tags ) {
	}

	/**
	 * Iterate the fixed source.
	 *
	 * @param int $batch_id Batch identifier.
	 * @return iterable<BatchExportSourceTag>
	 */
	public function iterate_for_batch( int $batch_id ): iterable {
		unset( $batch_id );

		yield from $this->tags;
	}
}
