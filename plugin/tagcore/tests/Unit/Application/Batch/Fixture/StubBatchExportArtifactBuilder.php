<?php
/**
 * Stub Batch export artifact builder fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture;

use ReturnTag\TagCore\Application\Batch\BatchExportArtifact;
use ReturnTag\TagCore\Application\Batch\BatchExportArtifactBuilder;
use ReturnTag\TagCore\Application\Batch\BatchExportSourceTag;
use ReturnTag\TagCore\Application\Persistence\Record\BatchRecord;

/**
 * Returns one fixed prepared artifact.
 */
final readonly class StubBatchExportArtifactBuilder implements BatchExportArtifactBuilder {
	/**
	 * Create the builder.
	 *
	 * @param BatchExportArtifact $artifact Fixed artifact.
	 */
	public function __construct( private BatchExportArtifact $artifact ) {
	}

	/**
	 * Return the fixed artifact after consuming the source.
	 *
	 * @param BatchRecord $batch Batch snapshot.
	 * @param iterable    $tags Source Tags.
	 * @phpstan-param iterable<BatchExportSourceTag> $tags
	 */
	public function build( BatchRecord $batch, iterable $tags ): BatchExportArtifact {
		unset( $batch );

		foreach ( $tags as $tag ) {
			unset( $tag );
		}

		return $this->artifact;
	}
}
