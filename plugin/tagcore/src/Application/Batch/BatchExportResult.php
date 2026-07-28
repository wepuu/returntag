<?php
/**
 * Audited Batch export result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Application\Persistence\Record\BatchExportRecord;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;

/**
 * Couples one committed audit record with its exact temporary artifact.
 */
final readonly class BatchExportResult {
	/**
	 * Create an export result.
	 *
	 * @param string              $batch_code Safe Batch Code.
	 * @param BatchStatus         $batch_status State after commit.
	 * @param BatchExportRecord   $record Immutable audit record.
	 * @param BatchExportArtifact $artifact Prepared file.
	 */
	public function __construct(
		public string $batch_code,
		public BatchStatus $batch_status,
		public BatchExportRecord $record,
		public BatchExportArtifact $artifact
	) {
	}
}
