<?php
/**
 * Account Conversation continuation boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

use ReturnTag\TagCore\Application\Account\ContinueOwnerConversation;
use Throwable;

/** Accepts one nonce-protected Conversation selector and no authority input. */
final readonly class AccountConversationFormHandler {
	public const NONCE_ACTION = 'returntag_account_conversations';

	public const NONCE_FIELD = 'returntag_account_conversation_nonce';

	public const ACTION_FIELD = 'returntag_account_conversation_action';

	public const CONVERSATION_FIELD = 'returntag_conversation_id';

	public const CONTINUE_ACTION = 'continue_securely';

	/**
	 * Create the continuation form boundary.
	 *
	 * @param ContinueOwnerConversation|null $continuation Fail-closed continuation service.
	 * @param AccountFormRequestGuard        $guard Same-site request guard.
	 */
	public function __construct(
		private ?ContinueOwnerConversation $continuation,
		private AccountFormRequestGuard $guard
	) {
	}

	/** Validate and execute one explicit continuation POST. */
	public function submit(): AccountConversationFormResult {
		if (
			null === $this->continuation
			|| ! $this->guard->is_same_site()
			|| ! $this->guard->valid_nonce( self::NONCE_FIELD, self::NONCE_ACTION )
			|| self::CONTINUE_ACTION !== $this->guard->post_string( self::ACTION_FIELD, 32 )
			|| $this->contains_forbidden_authority_input()
		) {
			return new AccountConversationFormResult( AccountConversationFeedback::UNAVAILABLE );
		}

		$value = $this->guard->post_string( self::CONVERSATION_FIELD, 20 );

		if ( 1 !== preg_match( '/^[1-9][0-9]{0,18}$/D', $value ) ) {
			return new AccountConversationFormResult( AccountConversationFeedback::UNAVAILABLE );
		}

		try {
			$result = $this->continuation->execute( (int) $value );
		} catch ( Throwable ) {
			return new AccountConversationFormResult( AccountConversationFeedback::UNAVAILABLE );
		}

		return $result->continued && null !== $result->session
			? new AccountConversationFormResult( AccountConversationFeedback::NONE, $result->session )
			: new AccountConversationFormResult( AccountConversationFeedback::UNAVAILABLE );
	}

	/** Reject browser attempts to supply server-owned authority fields. */
	private function contains_forbidden_authority_input(): bool {
		foreach ( array( 'owner_id', 'tag_id', 'actor_role', 'conversation_status', 'authorization_result' ) as $field ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified before this method is called.
			if ( array_key_exists( $field, $_POST ) ) {
				return true;
			}
		}

		return false;
	}
}
