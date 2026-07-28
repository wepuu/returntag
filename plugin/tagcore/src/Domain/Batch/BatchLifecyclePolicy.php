<?php
/**
 * Batch lifecycle transition policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\Batch;

/**
 * Defines only approved RT-208 state edges without persistence concerns.
 */
final class BatchLifecyclePolicy {
	/**
	 * Determine whether the requested action may change the current status.
	 *
	 * @param BatchStatus          $current Current persisted status.
	 * @param BatchLifecycleAction $action Requested operator action.
	 */
	public function allows( BatchStatus $current, BatchLifecycleAction $action ): bool {
		return match ( $action ) {
			BatchLifecycleAction::RELEASE => in_array(
				$current,
				array( BatchStatus::EXPORTED, BatchStatus::SUSPENDED ),
				true
			),
			BatchLifecycleAction::SUSPEND => in_array(
				$current,
				array( BatchStatus::GENERATED, BatchStatus::EXPORTED, BatchStatus::RELEASED ),
				true
			),
			BatchLifecycleAction::VOID => in_array(
				$current,
				array(
					BatchStatus::GENERATED,
					BatchStatus::EXPORTED,
					BatchStatus::RELEASED,
					BatchStatus::SUSPENDED,
				),
				true
			),
		};
	}

	/**
	 * Determine whether an action already reached its target.
	 *
	 * @param BatchStatus          $current Current persisted status.
	 * @param BatchLifecycleAction $action Requested operator action.
	 */
	public function is_idempotent( BatchStatus $current, BatchLifecycleAction $action ): bool {
		return $current === $this->target_status( $action );
	}

	/**
	 * Return the persisted target status.
	 *
	 * @param BatchLifecycleAction $action Requested operator action.
	 */
	public function target_status( BatchLifecycleAction $action ): BatchStatus {
		return match ( $action ) {
			BatchLifecycleAction::RELEASE => BatchStatus::RELEASED,
			BatchLifecycleAction::SUSPEND => BatchStatus::SUSPENDED,
			BatchLifecycleAction::VOID => BatchStatus::VOIDED,
		};
	}

	/**
	 * Return the approved audit Event type.
	 *
	 * @param BatchLifecycleAction $action Requested operator action.
	 */
	public function event_type( BatchLifecycleAction $action ): string {
		return match ( $action ) {
			BatchLifecycleAction::RELEASE => 'batch_released',
			BatchLifecycleAction::SUSPEND => 'batch_suspended',
			BatchLifecycleAction::VOID => 'batch_voided',
		};
	}
}
