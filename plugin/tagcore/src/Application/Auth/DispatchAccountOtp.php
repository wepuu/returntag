<?php
/**
 * Dispatch one Owner Account OTP from a Worker.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use DateInterval;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;

/**
 * Generates plaintext only in Worker memory and claims before mail dispatch.
 */
final readonly class DispatchAccountOtp {
	private const VALID_FOR = 'PT10M';

	/**
	 * Create the Account OTP Worker use case.
	 *
	 * @param FeatureFlagReader          $feature_flags Operational controls.
	 * @param AccountOtpStore            $store Atomic challenge persistence.
	 * @param AccountOtpProtector        $protector Sensitive-data protection.
	 * @param ActivationOtpCodeGenerator $codes In-memory code generator.
	 * @param AccountOtpEmailSender      $email Transactional mailer.
	 * @param Clock                      $clock UTC clock.
	 */
	public function __construct(
		private FeatureFlagReader $feature_flags,
		private AccountOtpStore $store,
		private AccountOtpProtector $protector,
		private ActivationOtpCodeGenerator $codes,
		private AccountOtpEmailSender $email,
		private Clock $clock
	) {
	}

	/**
	 * Dispatch one internal challenge at most once.
	 *
	 * @param int $challenge_id Positive challenge identifier.
	 */
	public function execute( int $challenge_id ): AccountOtpDispatchResult {
		if (
			$challenge_id < 1
			|| ! $this->feature_flags->is_enabled( FeatureFlag::OWNER_ACCOUNT )
			|| ! $this->feature_flags->is_enabled( FeatureFlag::EMAIL_DISPATCH )
		) {
			return AccountOtpDispatchResult::NO_ACTION;
		}

		$challenge = $this->store->find_by_id( $challenge_id );

		if (
			null === $challenge
			|| RequestAccountOtp::PURPOSE !== $challenge->data->purpose
			|| RequestAccountOtp::SUBJECT_TYPE !== $challenge->data->subject_type
			|| 0 !== $challenge->data->send_count
			|| null !== $challenge->data->consumed_at
		) {
			return AccountOtpDispatchResult::NO_ACTION;
		}

		try {
			$subject = LookupDigest::from_digest( $challenge->data->subject_id );
		} catch ( \InvalidArgumentException ) {
			return AccountOtpDispatchResult::NO_ACTION;
		}

		$now  = $this->clock->now();
		$code = $this->codes->generate();

		try {
			$claimed = $this->store->claim_for_dispatch(
				$challenge_id,
				$this->protector->hash_code( $code ),
				$now->add( new DateInterval( self::VALID_FOR ) ),
				$now
			);

			if ( null === $claimed ) {
				return AccountOtpDispatchResult::NO_ACTION;
			}

			$recipient = $this->protector->decrypt_email( $claimed->data->email_ciphertext, $subject );

			$idempotency_key = hash( 'sha256', "returntag:account-otp:v1\0" . $challenge_id );
			return $this->email->send( $recipient, $code, $idempotency_key )
				? AccountOtpDispatchResult::ACCEPTED_BY_MAILER
				: AccountOtpDispatchResult::MAILER_REJECTED;
		} finally {
			if ( function_exists( 'sodium_memzero' ) ) {
				sodium_memzero( $code );
			}

			$code = '';
		}
	}
}
