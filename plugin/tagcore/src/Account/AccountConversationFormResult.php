<?php
/**
 * Account Conversation continuation form result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

/** Carries a raw session only until the Account adapter sets its secure cookie. */
final readonly class AccountConversationFormResult {
	/**
	 * Create one form result.
	 *
	 * @param AccountConversationFeedback $feedback Closed user-safe feedback.
	 * @param string|null                 $session Raw session returned only to the cookie adapter.
	 */
	public function __construct(
		public AccountConversationFeedback $feedback = AccountConversationFeedback::NONE,
		public ?string $session = null
	) {
	}
}
