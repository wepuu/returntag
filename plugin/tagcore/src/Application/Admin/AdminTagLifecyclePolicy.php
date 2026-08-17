<?php
/**
 * Administrator Tag lifecycle transition policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

use ReturnTag\TagCore\Domain\Tag\TagStatus;

/** Decides the frozen RT-327 transition without infrastructure dependencies. */
final class AdminTagLifecyclePolicy {
	/**
	 * Return the committed state, or null when the action must fail closed.
	 *
	 * @param AdminTagLifecycleAction $action Administrator action.
	 * @param AdminTagLifecycleState  $before Locked current state.
	 * @param int|null                $target_user_id Optional target User ID.
	 */
	public function decide( AdminTagLifecycleAction $action, AdminTagLifecycleState $before, ?int $target_user_id ): ?AdminTagLifecycleState {
		if ( TagStatus::RETIRED === $before->status ) {
			return null;
		}

		if ( AdminTagLifecycleAction::SUSPEND === $action ) {
			return in_array( $before->status, array( TagStatus::UNREGISTERED, TagStatus::ACTIVE ), true )
				? new AdminTagLifecycleState( TagStatus::SUSPENDED, $before->owner_id )
				: null;
		}
		if ( AdminTagLifecycleAction::RETIRE === $action ) {
			return new AdminTagLifecycleState( TagStatus::RETIRED, $before->owner_id );
		}
		if ( AdminTagLifecycleAction::REMOVE_OWNER === $action ) {
			return null !== $before->owner_id && in_array( $before->status, array( TagStatus::ACTIVE, TagStatus::SUSPENDED ), true )
				? new AdminTagLifecycleState( TagStatus::SUSPENDED, null )
				: null;
		}

		if (
			null === $before->owner_id
			|| null === $target_user_id
			|| $target_user_id < 1
			|| $target_user_id === $before->owner_id
			|| ! in_array( $before->status, array( TagStatus::ACTIVE, TagStatus::SUSPENDED ), true )
		) {
			return null;
		}

		return new AdminTagLifecycleState( $before->status, $target_user_id );
	}
}
