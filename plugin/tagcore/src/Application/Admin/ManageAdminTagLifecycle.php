<?php
/**
 * Administrator Tag lifecycle use case.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Domain\Tag\TagId;

/** Enforces the kill switch and exact confirmation before persistence. */
final readonly class ManageAdminTagLifecycle {
	/**
	 * Create the lifecycle use case.
	 *
	 * @param AdminTagLifecycleStore $store Atomic persistence port.
	 * @param FeatureFlagReader      $flags Operational feature controls.
	 * @param Clock                  $clock UTC clock.
	 */
	public function __construct( private AdminTagLifecycleStore $store, private FeatureFlagReader $flags, private Clock $clock ) {
	}

	/**
	 * Execute one authorized action after boundary validation.
	 *
	 * @param TagId                   $tag_id Canonical Tag ID.
	 * @param AdminTagLifecycleAction $action Administrator action.
	 * @param string                  $confirmation Exact canonical Tag ID confirmation.
	 * @param AdminTagLifecycleState  $expected Submitted current-state snapshot.
	 * @param int|null                $target_user_id Optional target WordPress User ID.
	 * @param int                     $operator_id Operator WordPress User ID.
	 */
	public function execute(
		TagId $tag_id,
		AdminTagLifecycleAction $action,
		string $confirmation,
		AdminTagLifecycleState $expected,
		?int $target_user_id,
		int $operator_id
	): AdminTagLifecycleResult {
		if (
			$operator_id < 1
			|| $confirmation !== $tag_id->value
			|| ! $this->flags->is_enabled( FeatureFlag::ADMIN_TAG_LIFECYCLE )
		) {
			return AdminTagLifecycleResult::unavailable();
		}

		return $this->store->change( $tag_id, $action, $expected, $target_user_id, $operator_id, $this->clock->now() );
	}
}
