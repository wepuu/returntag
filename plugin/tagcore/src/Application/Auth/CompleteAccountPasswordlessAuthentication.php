<?php
/**
 * Verify an Owner Account OTP and establish a WordPress session.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Domain\Auth\ActivationOtpCode;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/**
 * Verifies an Account challenge before provisioning and authenticating.
 */
final readonly class CompleteAccountPasswordlessAuthentication {
	private const MAXIMUM_ATTEMPTS = 5;

	/**
	 * Create the Account passwordless authentication use case.
	 *
	 * @param FeatureFlagReader              $feature_flags Operational controls.
	 * @param AccountOtpStore                $store Atomic challenge persistence.
	 * @param AccountOtpProtector            $protector Sensitive-data protection.
	 * @param AccountOtpRateLimiter          $rate_limiter Account-specific budgets.
	 * @param PasswordlessAccountProvisioner $accounts WordPress account boundary.
	 * @param AuthenticatedSession           $session Native WordPress session.
	 * @param WordPressAccountEmailPolicy    $email_policy WordPress email boundary.
	 * @param Clock                          $clock UTC clock.
	 */
	public function __construct(
		private FeatureFlagReader $feature_flags,
		private AccountOtpStore $store,
		private AccountOtpProtector $protector,
		private AccountOtpRateLimiter $rate_limiter,
		private PasswordlessAccountProvisioner $accounts,
		private AuthenticatedSession $session,
		private WordPressAccountEmailPolicy $email_policy,
		private Clock $clock
	) {
	}

	/**
	 * Verify one code and establish a non-persistent WordPress session.
	 *
	 * @param EmailAddress      $email Canonical requested email.
	 * @param ActivationOtpCode $code Exact six-digit code.
	 * @param string            $ip_address Direct-peer IP address.
	 */
	public function execute(
		EmailAddress $email,
		ActivationOtpCode $code,
		string $ip_address
	): PasswordlessAuthenticationResult {
		if ( null !== $this->session->current_user_id() ) {
			return PasswordlessAuthenticationResult::ALREADY_AUTHENTICATED;
		}

		if (
			! $this->email_policy->allows( $email )
			|| ! $this->feature_flags->is_enabled( FeatureFlag::OWNER_ACCOUNT )
		) {
			return PasswordlessAuthenticationResult::INVALID;
		}

		$now          = $this->clock->now();
		$email_lookup = $this->protector->email_lookup( $email );
		$ip_lookup    = $this->protector->ip_lookup( $ip_address );

		if ( ! $this->rate_limiter->reserve_verification_ip( $ip_lookup, $now ) ) {
			return PasswordlessAuthenticationResult::INVALID;
		}

		if (
			! $this->store->has_verifiable_latest( $email_lookup, $now, self::MAXIMUM_ATTEMPTS )
			|| ! $this->rate_limiter->reserve_verification_email( $email_lookup, $now )
			|| ActivationOtpVerificationResult::VERIFIED !== $this->store->verify_latest(
				$email_lookup,
				$now,
				self::MAXIMUM_ATTEMPTS,
				fn( \ReturnTag\TagCore\Application\Persistence\Value\OtpHash $hash ): bool => $this->protector->verify_code( $code->value, $hash )
			)
		) {
			return PasswordlessAuthenticationResult::INVALID;
		}

		$user_id = $this->accounts->provision( $email, $email_lookup, $now );
		$this->session->authenticate( $user_id );

		return PasswordlessAuthenticationResult::AUTHENTICATED;
	}
}
