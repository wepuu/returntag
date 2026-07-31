<?php
/**
 * Request one activation OTP.
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
use ReturnTag\TagCore\Application\PublicTag\PublicTagPageState;
use ReturnTag\TagCore\Application\PublicTag\ResolvePublicTagPage;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Tag\TagId;
use Throwable;

/**
 * Persists an unissued challenge before scheduling a challenge-ID-only action.
 */
final readonly class RequestActivationOtp {
	public const PURPOSE = 'activation_otp';

	public const SUBJECT_TYPE = 'tag';

	private const VALID_FOR = 'PT10M';

	/**
	 * Create the OTP request use case.
	 *
	 * @param ResolvePublicTagPage        $pages Public state resolver.
	 * @param FeatureFlagReader           $feature_flags Operational controls.
	 * @param ActivationOtpRequestStore   $store Challenge persistence.
	 * @param ActivationOtpProtector      $protector Sensitive-data protection.
	 * @param ActivationOtpRateLimiter    $rate_limiter IP and global limiter.
	 * @param ActivationOtpScheduler      $scheduler Challenge scheduler.
	 * @param WordPressAccountEmailPolicy $email_policy WordPress storage limit.
	 * @param Clock                       $clock UTC clock.
	 */
	public function __construct(
		private ResolvePublicTagPage $pages,
		private FeatureFlagReader $feature_flags,
		private ActivationOtpRequestStore $store,
		private ActivationOtpProtector $protector,
		private ActivationOtpRateLimiter $rate_limiter,
		private ActivationOtpScheduler $scheduler,
		private WordPressAccountEmailPolicy $email_policy,
		private Clock $clock
	) {
	}

	/**
	 * Request one challenge without generating an OTP in the public request.
	 *
	 * @param TagId        $tag_id Eligible public Tag.
	 * @param EmailAddress $email Canonical recipient identity.
	 * @param string       $ip_address Direct client IP.
	 */
	public function execute( TagId $tag_id, EmailAddress $email, string $ip_address ): ActivationOtpRequestResult {
		if ( ! $this->email_policy->allows( $email ) ) {
			return ActivationOtpRequestResult::UNAVAILABLE;
		}

		if (
			! $this->feature_flags->is_enabled( FeatureFlag::GLOBAL_ACTIVATION )
			|| ! $this->feature_flags->is_enabled( FeatureFlag::EMAIL_DISPATCH )
			|| PublicTagPageState::ACTIVATION_ENTRY !== $this->pages->execute( $tag_id, null )->state
		) {
			return ActivationOtpRequestResult::UNAVAILABLE;
		}

		$now          = $this->clock->now();
		$email_lookup = $this->protector->email_lookup( $email );
		$ip_lookup    = $this->protector->ip_lookup( $ip_address );

		if (
			$this->limit_reached( $email_lookup, $tag_id, $now )
			|| ! $this->rate_limiter->reserve( $ip_lookup, $email_lookup, $tag_id, $now )
		) {
			return ActivationOtpRequestResult::THROTTLED;
		}

		$challenge = $this->store->create_replacing(
			new NewAuthChallengeRecord(
				self::PURPOSE,
				self::SUBJECT_TYPE,
				$tag_id->value,
				$this->protector->encrypt_email( $email, $tag_id ),
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

			return ActivationOtpRequestResult::UNAVAILABLE;
		}

		return ActivationOtpRequestResult::ACCEPTED;
	}

	/**
	 * Apply indexed persistent email and Tag budgets.
	 *
	 * @param \ReturnTag\TagCore\Application\Persistence\Value\LookupDigest $email_lookup Keyed email digest.
	 * @param TagId                                                         $tag_id Public Tag.
	 * @param \DateTimeImmutable                                            $now Current UTC time.
	 */
	private function limit_reached(
		\ReturnTag\TagCore\Application\Persistence\Value\LookupDigest $email_lookup,
		TagId $tag_id,
		\DateTimeImmutable $now
	): bool {
		$windows = array(
			array( new DateInterval( 'PT1M' ), 1, 3 ),
			array( new DateInterval( 'PT1H' ), 5, 20 ),
			array( new DateInterval( 'P1D' ), 10, 50 ),
		);

		foreach ( $windows as [ $interval, $email_limit, $tag_limit ] ) {
			$since = $now->sub( $interval );

			if (
				$this->store->count_recent_for_email( $email_lookup, $since ) >= $email_limit
				|| $this->store->count_recent_for_tag( $tag_id, $since ) >= $tag_limit
			) {
				return true;
			}
		}

		return false;
	}
}
