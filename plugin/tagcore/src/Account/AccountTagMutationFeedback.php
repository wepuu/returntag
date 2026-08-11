<?php
/**
 * Owner Tag mutation feedback.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

/** Carries no submitted or stored private values. */
final readonly class AccountTagMutationFeedback {
	/**
	 * Create privacy-safe mutation feedback.
	 *
	 * @param AccountTagMutationState $state Closed feedback state.
	 */
	public function __construct( public AccountTagMutationState $state = AccountTagMutationState::NONE ) {
	}
}
