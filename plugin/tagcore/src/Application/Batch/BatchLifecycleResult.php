<?php
/**
 * Batch lifecycle command/query result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

/**
 * Complete privacy-safe state returned to the administration adapter.
 */
final readonly class BatchLifecycleResult {
	/**
	 * Effective result after global and Batch controls.
	 *
	 * @var bool
	 */
	public bool $effective_activation_enabled;

	/**
	 * Create one lifecycle result.
	 *
	 * @param BatchLifecycleState $state Persisted Batch state.
	 * @param BatchTagCounts      $tag_counts Aggregate Tag status counts.
	 * @param bool                $global_activation_enabled Global incident control.
	 * @param bool                $release_ready Whether immutable release evidence is complete.
	 * @param bool                $changed Whether this request changed storage.
	 */
	public function __construct(
		public BatchLifecycleState $state,
		public BatchTagCounts $tag_counts,
		public bool $global_activation_enabled,
		public bool $release_ready,
		public bool $changed
	) {
		$this->effective_activation_enabled = $this->global_activation_enabled
			&& $this->state->activation_enabled
			&& \ReturnTag\TagCore\Domain\Batch\BatchStatus::RELEASED === $this->state->batch_status;
	}
}
