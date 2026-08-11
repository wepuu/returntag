<?php
/**
 * Owner Account sign-in form result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

/**
 * Carries only presentation-safe sign-in feedback and the submitted email.
 */
final readonly class AccountFormResult {
	/**
	 * Create one form result.
	 *
	 * @param AccountFormState $state Closed presentation state.
	 * @param string           $email Submitted email retained only for the next form.
	 */
	public function __construct(
		public AccountFormState $state,
		public string $email = ''
	) {
	}
}
