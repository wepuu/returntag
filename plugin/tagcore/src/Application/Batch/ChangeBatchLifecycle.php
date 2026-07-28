<?php
/**
 * Transactional Batch lifecycle transition.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Application\Batch\Exception\BatchLifecycleConflict;
use ReturnTag\TagCore\Application\Batch\Exception\BatchLifecycleIntegrityViolation;
use ReturnTag\TagCore\Application\Batch\Exception\BatchLifecycleNotAllowed;
use ReturnTag\TagCore\Application\Batch\Exception\BatchLifecycleNotFound;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Domain\Batch\BatchLifecycleAction;
use ReturnTag\TagCore\Domain\Batch\BatchLifecyclePolicy;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;

/**
 * Locks, validates, changes, and audits one Batch atomically.
 */
final readonly class ChangeBatchLifecycle {
	/**
	 * Create the transition service.
	 *
	 * @param BatchLifecycleRepository $batches Lifecycle persistence.
	 * @param EventRepository          $events Audit Event persistence.
	 * @param TransactionManager       $transactions Transaction boundary.
	 * @param FeatureFlagReader        $feature_flags Global incident controls.
	 * @param Clock                    $clock UTC clock.
	 * @param BatchLifecyclePolicy     $policy Domain transition policy.
	 */
	public function __construct(
		private BatchLifecycleRepository $batches,
		private EventRepository $events,
		private TransactionManager $transactions,
		private FeatureFlagReader $feature_flags,
		private Clock $clock,
		private BatchLifecyclePolicy $policy
	) {
	}

	/**
	 * Execute one authorized operator transition.
	 *
	 * @param int                  $batch_id Batch identifier.
	 * @param int                  $actor_id Authorized WordPress User ID.
	 * @param BatchStatus          $expected_status Client-observed status.
	 * @param BatchLifecycleAction $action Requested transition.
	 * @throws \ReturnTag\TagCore\Application\Batch\Exception\BatchLifecycleException When the transition fails safely.
	 */
	public function execute(
		int $batch_id,
		int $actor_id,
		BatchStatus $expected_status,
		BatchLifecycleAction $action
	): BatchLifecycleResult {
		if ( $batch_id < 1 || $actor_id < 1 ) {
			throw new BatchLifecycleNotAllowed( 'Batch lifecycle request is invalid.' );
		}

		$result = $this->transactions->transactional(
			function () use ( $batch_id, $actor_id, $expected_status, $action ): array {
				$state = $this->batches->lock_by_id( $batch_id );

				if ( null === $state ) {
					throw new BatchLifecycleNotFound( 'Batch was not found.' );
				}

				$counts           = $this->batches->count_tags_by_status( $batch_id );
				$export_row_count = $this->batches->latest_export_row_count( $batch_id );
				$release_ready    = $this->release_is_ready( $state, $counts, $export_row_count );

				if ( $this->policy->is_idempotent( $state->batch_status, $action ) ) {
					$this->assert_storage_is_consistent( $state, $counts );
					return array( $state, $counts, $release_ready, false );
				}

				if ( $state->batch_status !== $expected_status ) {
					throw new BatchLifecycleConflict( 'Batch status changed before the request completed.' );
				}

				if ( ! $this->policy->allows( $state->batch_status, $action ) ) {
					throw new BatchLifecycleNotAllowed( 'Batch status does not allow this lifecycle action.' );
				}

				$this->assert_storage_is_consistent( $state, $counts );

				if ( BatchLifecycleAction::RELEASE === $action ) {
					$this->assert_release_is_audited( $release_ready );
				}

				$target             = $this->policy->target_status( $action );
				$activation_enabled = BatchStatus::RELEASED === $target;
				$now                = $this->clock->now();

				if (
					! $this->batches->transition(
						$batch_id,
						$state->batch_status,
						$target,
						$activation_enabled,
						$now
					)
				) {
					throw new BatchLifecycleConflict( 'Batch status changed before the request completed.' );
				}

				$this->events->append(
					new NewEventRecord(
						$this->policy->event_type( $action ),
						'user',
						$actor_id,
						'batch',
						(string) $batch_id,
						'success',
						null,
						EventMetadata::none(),
						$now
					)
				);

				return array(
					new BatchLifecycleState(
						$state->batch_id,
						$state->batch_code,
						$state->requested_quantity,
						$state->generated_quantity,
						$target,
						$activation_enabled,
						$now
					),
					$counts,
					$release_ready,
					true,
				);
			}
		);

		/**
		 * Locked or newly changed state.
		 *
		 * @var BatchLifecycleState $state
		 */
		$state = $result[0];
		/**
		 * Aggregate Tag counts read in the transaction.
		 *
		 * @var BatchTagCounts $counts
		 */
		$counts = $result[1];

		return new BatchLifecycleResult(
			$state,
			$counts,
			$this->feature_flags->is_enabled( FeatureFlag::GLOBAL_ACTIVATION ),
			(bool) $result[2],
			(bool) $result[3]
		);
	}

	/**
	 * Verify the materialized count and activation invariant.
	 *
	 * @param BatchLifecycleState $state Locked Batch state.
	 * @param BatchTagCounts      $counts Aggregate Tag counts.
	 * @throws BatchLifecycleIntegrityViolation When stored lifecycle data is inconsistent.
	 */
	private function assert_storage_is_consistent(
		BatchLifecycleState $state,
		BatchTagCounts $counts
	): void {
		if (
			$counts->total !== $state->generated_quantity
			|| $state->generated_quantity !== $state->requested_quantity
			|| (
				BatchStatus::RELEASED === $state->batch_status
					? ! $state->activation_enabled
					: $state->activation_enabled
			)
		) {
			throw new BatchLifecycleIntegrityViolation( 'Batch lifecycle storage is inconsistent.' );
		}
	}

	/**
	 * Require one matching immutable manufacturer export before release.
	 *
	 * @param bool $release_ready Whether immutable release evidence is complete.
	 * @throws BatchLifecycleIntegrityViolation When no matching export exists.
	 */
	private function assert_release_is_audited( bool $release_ready ): void {
		if ( ! $release_ready ) {
			throw new BatchLifecycleIntegrityViolation( 'Batch release requires a complete audited export.' );
		}
	}

	/**
	 * Determine whether immutable manufacturing evidence supports release.
	 *
	 * @param BatchLifecycleState $state Current Batch state.
	 * @param BatchTagCounts      $counts Aggregate Tag counts.
	 * @param int|null            $export_row_count Latest audited row count.
	 */
	private function release_is_ready(
		BatchLifecycleState $state,
		BatchTagCounts $counts,
		?int $export_row_count
	): bool {
		return $state->generated_quantity === $state->requested_quantity
			&& $counts->total === $state->requested_quantity
			&& $export_row_count === $state->requested_quantity;
	}
}
