<?php
/**
 * Verify one activation OTP.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPageState;
use ReturnTag\TagCore\Application\PublicTag\ResolvePublicTagPage;
use ReturnTag\TagCore\Domain\Auth\ActivationOtpCode;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Coordinates eligibility, throttling, and atomic one-time verification.
 */
final readonly class VerifyActivationOtp {
	public const MAXIMUM_ATTEMPTS = 5;

	/**
	 * Create the verification use case.
	 *
	 * @param ResolvePublicTagPage                 $pages Current public Tag state.
	 * @param FeatureFlagReader                    $feature_flags Operational controls.
	 * @param ActivationOtpVerificationStore       $store Atomic challenge persistence.
	 * @param ActivationOtpProtector               $protector Keyed lookups and comparison.
	 * @param ActivationOtpVerificationRateLimiter $rate_limiter Verification budgets.
	 * @param Clock                                $clock UTC clock.
	 */
	public function __construct(
		private ResolvePublicTagPage $pages,
		private FeatureFlagReader $feature_flags,
		private ActivationOtpVerificationStore $store,
		private ActivationOtpProtector $protector,
		private ActivationOtpVerificationRateLimiter $rate_limiter,
		private Clock $clock
	) {
	}

	/**
	 * Verify one code without logging in, creating a user, or activating a Tag.
	 *
	 * @param TagId             $tag_id Server-resolved eligible Tag.
	 * @param EmailAddress      $email Canonical email identity.
	 * @param ActivationOtpCode $code Exact six-digit code.
	 * @param string            $ip_address Direct client IP.
	 */
	public function execute(
		TagId $tag_id,
		EmailAddress $email,
		ActivationOtpCode $code,
		string $ip_address
	): ActivationOtpVerificationResult {
		if (
			! $this->feature_flags->is_enabled( FeatureFlag::GLOBAL_ACTIVATION )
			|| PublicTagPageState::ACTIVATION_ENTRY !== $this->pages->execute( $tag_id, null )->state
		) {
			return ActivationOtpVerificationResult::UNAVAILABLE;
		}

		$now          = $this->clock->now();
		$email_lookup = $this->protector->email_lookup( $email );
		$ip_lookup    = $this->protector->ip_lookup( $ip_address );

		if ( ! $this->rate_limiter->reserve_public( $ip_lookup, $tag_id, $now ) ) {
			return ActivationOtpVerificationResult::THROTTLED;
		}

		if (
			! $this->store->has_verifiable_latest(
				$tag_id,
				$email_lookup,
				$now,
				self::MAXIMUM_ATTEMPTS
			)
		) {
			return ActivationOtpVerificationResult::INVALID;
		}

		if ( ! $this->rate_limiter->reserve_email( $email_lookup, $now ) ) {
			return ActivationOtpVerificationResult::THROTTLED;
		}

		return $this->store->verify_latest(
			$tag_id,
			$email_lookup,
			$now,
			self::MAXIMUM_ATTEMPTS,
			fn( OtpHash $hash ): bool => $this->protector->verify_code( $code->value, $hash )
		);
	}
}
