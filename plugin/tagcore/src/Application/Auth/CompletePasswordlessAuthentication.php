<?php
/**
 * Complete activation passwordless authentication.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Domain\Auth\ActivationOtpCode;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Composes one-time verification, account provisioning, and session creation.
 */
final readonly class CompletePasswordlessAuthentication {
	/**
	 * Create the authentication use case.
	 *
	 * @param ActivationOtpVerifier          $verification Atomic OTP verification.
	 * @param ActivationOtpProtector         $protector Keyed lookup derivation.
	 * @param PasswordlessAccountProvisioner $accounts WordPress account boundary.
	 * @param AuthenticatedSession           $session WordPress session boundary.
	 * @param WordPressAccountEmailPolicy    $email_policy WordPress storage limit.
	 * @param Clock                          $clock UTC clock.
	 */
	public function __construct(
		private ActivationOtpVerifier $verification,
		private ActivationOtpProtector $protector,
		private PasswordlessAccountProvisioner $accounts,
		private AuthenticatedSession $session,
		private WordPressAccountEmailPolicy $email_policy,
		private Clock $clock
	) {
	}

	/**
	 * Authenticate without creating browser handoff state or activating a Tag.
	 *
	 * An existing authenticated session wins before OTP verification so stale
	 * forms cannot consume a code or silently switch accounts.
	 *
	 * @param TagId             $tag_id Eligible public Tag.
	 * @param EmailAddress      $email Canonical email identity.
	 * @param ActivationOtpCode $code Exact six-digit code.
	 * @param string            $ip_address Direct client IP.
	 */
	public function execute(
		TagId $tag_id,
		EmailAddress $email,
		ActivationOtpCode $code,
		string $ip_address
	): PasswordlessAuthenticationResult {
		if ( null !== $this->session->current_user_id() ) {
			return PasswordlessAuthenticationResult::ALREADY_AUTHENTICATED;
		}

		if ( ! $this->email_policy->allows( $email ) ) {
			return PasswordlessAuthenticationResult::INVALID;
		}

		if (
			ActivationOtpVerificationResult::VERIFIED
			!== $this->verification->execute( $tag_id, $email, $code, $ip_address )
		) {
			return PasswordlessAuthenticationResult::INVALID;
		}

		$user_id = $this->accounts->provision(
			$email,
			$this->protector->email_lookup( $email ),
			$this->clock->now()
		);

		$this->session->authenticate( $user_id );

		return PasswordlessAuthenticationResult::AUTHENTICATED;
	}
}
