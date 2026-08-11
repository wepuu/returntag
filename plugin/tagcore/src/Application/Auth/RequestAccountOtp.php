<?php
/**
 * Request one Owner Account OTP.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use DateInterval;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\Record\NewAuthChallengeRecord;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use Throwable;

/**
 * Persists an Account-scoped challenge before scheduling delivery.
 */
final readonly class RequestAccountOtp {
	public const PURPOSE = 'account_otp';

	public const SUBJECT_TYPE = 'account';

	private const VALID_FOR = 'PT10M';

	/**
	 * Create the Account OTP request use case.
	 *
	 * @param FeatureFlagReader           $feature_flags Operational controls.
	 * @param AccountOtpStore             $store Atomic challenge persistence.
	 * @param AccountOtpProtector         $protector Sensitive-data protection.
	 * @param AccountOtpRateLimiter       $rate_limiter Account-specific budgets.
	 * @param AccountOtpScheduler         $scheduler Challenge-ID-only scheduler.
	 * @param WordPressAccountEmailPolicy $email_policy WordPress email boundary.
	 * @param Clock                       $clock UTC clock.
	 */
	public function __construct(
		private FeatureFlagReader $feature_flags,
		private AccountOtpStore $store,
		private AccountOtpProtector $protector,
		private AccountOtpRateLimiter $rate_limiter,
		private AccountOtpScheduler $scheduler,
		private WordPressAccountEmailPolicy $email_policy,
		private Clock $clock
	) {
	}

	/**
	 * Request one non-enumerating passwordless Account code.
	 *
	 * @param EmailAddress $email Canonical requested email.
	 * @param string       $ip_address Direct-peer IP address.
	 */
	public function execute( EmailAddress $email, string $ip_address ): AccountOtpRequestResult {
		if (
			! $this->email_policy->allows( $email )
			|| ! $this->feature_flags->is_enabled( FeatureFlag::OWNER_ACCOUNT )
			|| ! $this->feature_flags->is_enabled( FeatureFlag::EMAIL_DISPATCH )
		) {
			return AccountOtpRequestResult::UNAVAILABLE;
		}

		$now          = $this->clock->now();
		$email_lookup = $this->protector->email_lookup( $email );
		$ip_lookup    = $this->protector->ip_lookup( $ip_address );

		foreach ( array( array( 'PT1M', 1 ), array( 'PT1H', 5 ), array( 'P1D', 10 ) ) as [ $window, $limit ] ) {
			if ( $this->store->count_recent_for_email( $email_lookup, $now->sub( new DateInterval( $window ) ) ) >= $limit ) {
				return AccountOtpRequestResult::THROTTLED;
			}
		}

		if ( ! $this->rate_limiter->reserve_request( $ip_lookup, $email_lookup, $now ) ) {
			return AccountOtpRequestResult::THROTTLED;
		}

		$challenge = $this->store->create_replacing(
			new NewAuthChallengeRecord(
				self::PURPOSE,
				self::SUBJECT_TYPE,
				$email_lookup->value,
				$this->protector->encrypt_email( $email, $email_lookup ),
				$email_lookup,
				$this->protector->placeholder_hash(),
				0,
				0,
				$ip_lookup,
				$now->add( new DateInterval( self::VALID_FOR ) ),
				null,
				null,
				$now
			)
		);

		try {
			$this->scheduler->schedule( $challenge->challenge_id );
		} catch ( Throwable ) {
			$this->store->revoke_unissued( $challenge->challenge_id, $now );

			return AccountOtpRequestResult::UNAVAILABLE;
		}

		return AccountOtpRequestResult::ACCEPTED;
	}
}
