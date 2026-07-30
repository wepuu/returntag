<?php
/**
 * Generate and dispatch one activation OTP from a Worker.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use DateInterval;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPageState;
use ReturnTag\TagCore\Application\PublicTag\ResolvePublicTagPage;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Generates plaintext only in Worker memory and claims dispatch before email.
 */
final readonly class DispatchActivationOtp {
	private const VALID_FOR = 'PT10M';

	/**
	 * Create the Worker use case.
	 *
	 * @param ResolvePublicTagPage       $pages Public state resolver.
	 * @param FeatureFlagReader          $feature_flags Operational controls.
	 * @param ActivationOtpRequestStore  $store Challenge persistence.
	 * @param ActivationOtpProtector     $protector Sensitive-data protection.
	 * @param ActivationOtpCodeGenerator $codes In-memory code generator.
	 * @param ActivationOtpEmailSender   $email Transactional mailer.
	 * @param Clock                      $clock UTC clock.
	 */
	public function __construct(
		private ResolvePublicTagPage $pages,
		private FeatureFlagReader $feature_flags,
		private ActivationOtpRequestStore $store,
		private ActivationOtpProtector $protector,
		private ActivationOtpCodeGenerator $codes,
		private ActivationOtpEmailSender $email,
		private Clock $clock
	) {
	}

	/**
	 * Dispatch one internal challenge at most once.
	 *
	 * @param int $challenge_id Positive challenge identifier.
	 */
	public function execute( int $challenge_id ): ActivationOtpDispatchResult {
		if (
			$challenge_id < 1
			|| ! $this->feature_flags->is_enabled( FeatureFlag::GLOBAL_ACTIVATION )
			|| ! $this->feature_flags->is_enabled( FeatureFlag::EMAIL_DISPATCH )
		) {
			return ActivationOtpDispatchResult::NO_ACTION;
		}

		$challenge = $this->store->find_by_id( $challenge_id );

		if (
			null === $challenge
			|| RequestActivationOtp::PURPOSE !== $challenge->data->purpose
			|| RequestActivationOtp::SUBJECT_TYPE !== $challenge->data->subject_type
			|| 0 !== $challenge->data->send_count
			|| null !== $challenge->data->consumed_at
		) {
			return ActivationOtpDispatchResult::NO_ACTION;
		}

		try {
			$tag_id = TagId::from_canonical( $challenge->data->subject_id );
		} catch ( \InvalidArgumentException ) {
			return ActivationOtpDispatchResult::NO_ACTION;
		}

		if ( PublicTagPageState::ACTIVATION_ENTRY !== $this->pages->execute( $tag_id, null )->state ) {
			return ActivationOtpDispatchResult::NO_ACTION;
		}

		$now  = $this->clock->now();
		$code = $this->codes->generate();

		try {
			$sent = $this->store->claim_for_dispatch(
				$challenge_id,
				$this->protector->hash_code( $code ),
				$now->add( new DateInterval( self::VALID_FOR ) ),
				$now
			);

			if ( null === $sent ) {
				return ActivationOtpDispatchResult::NO_ACTION;
			}

			$recipient = $this->protector->decrypt_email( $sent->data->email_ciphertext, $tag_id );

			return $this->email->send( $recipient, $code )
				? ActivationOtpDispatchResult::ACCEPTED_BY_MAILER
				: ActivationOtpDispatchResult::MAILER_REJECTED;
		} finally {
			if ( function_exists( 'sodium_memzero' ) ) {
				sodium_memzero( $code );
			}

			$code = '';
		}
	}
}
