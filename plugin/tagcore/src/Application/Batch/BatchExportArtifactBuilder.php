<?php
/**
 * Batch export artifact builder port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Application\Persistence\Record\BatchRecord;

/**
 * Builds one deterministic export from a bounded streaming source.
 */
interface BatchExportArtifactBuilder {
	/**
	 * Build one CSV artifact.
	 *
	 * @param BatchRecord $batch Batch manufacturing snapshot.
	 * @param iterable    $tags Deterministically ordered Tags.
	 * @phpstan-param iterable<BatchExportSourceTag> $tags
	 */
	public function build( BatchRecord $batch, iterable $tags ): BatchExportArtifact;
}
