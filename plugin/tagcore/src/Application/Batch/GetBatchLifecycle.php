<?php
/**
 * Read Batch lifecycle state.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Application\Batch\Exception\BatchLifecycleIntegrityViolation;
use ReturnTag\TagCore\Application\Batch\Exception\BatchLifecycleNotFound;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;

/**
 * Returns privacy-safe counts and effective activation state.
 */
final readonly class GetBatchLifecycle {
	/**
	 * Create the query service.
	 *
	 * @param BatchLifecycleRepository $batches Lifecycle persistence.
	 * @param FeatureFlagReader        $feature_flags Global incident controls.
	 */
	public function __construct(
		private BatchLifecycleRepository $batches,
		private FeatureFlagReader $feature_flags
	) {
	}

	/**
	 * Return current privacy-safe lifecycle state.
	 *
	 * @param int $batch_id Batch identifier.
	 * @throws BatchLifecycleNotFound When the Batch is missing.
	 * @throws BatchLifecycleIntegrityViolation When persisted counters or controls conflict.
	 */
	public function execute( int $batch_id ): BatchLifecycleResult {
		if ( $batch_id < 1 ) {
			throw new BatchLifecycleNotFound( 'Batch was not found.' );
		}

		$state = $this->batches->find_by_id( $batch_id );

		if ( null === $state ) {
			throw new BatchLifecycleNotFound( 'Batch was not found.' );
		}

		$counts = $this->batches->count_tags_by_status( $batch_id );

		if (
			$counts->total !== $state->generated_quantity
			|| (
				BatchStatus::RELEASED === $state->batch_status
					? ! $state->activation_enabled
					: $state->activation_enabled
			)
		) {
			throw new BatchLifecycleIntegrityViolation( 'Batch lifecycle storage is inconsistent.' );
		}

		return new BatchLifecycleResult(
			$state,
			$counts,
			$this->feature_flags->is_enabled( FeatureFlag::GLOBAL_ACTIVATION ),
			$state->generated_quantity === $state->requested_quantity
				&& $counts->total === $state->requested_quantity
				&& $this->batches->latest_export_row_count( $batch_id ) === $state->requested_quantity,
			false
		);
	}
}
