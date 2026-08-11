<?php
/**
 * Account Conversation continuation outcome.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

/** Carries a raw session only on successful server-authorized issuance. */
final readonly class OwnerConversationContinuationResult {
	/**
	 * Create one generic continuation result.
	 *
	 * @param bool        $continued Whether a session was issued.
	 * @param string|null $session Raw session for the cookie boundary only.
	 */
	private function __construct(
		public bool $continued,
		public ?string $session = null
	) {
	}

	/**
	 * Return one successful short-session result.
	 *
	 * @param string $session Raw role-bound session.
	 */
	public static function continued( string $session ): self {
		return new self( true, $session );
	}

	/** Return one generic unavailable result. */
	public static function unavailable(): self {
		return new self( false );
	}
}
