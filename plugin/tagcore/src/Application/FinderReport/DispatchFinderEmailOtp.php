<?php
/**
 * Background Finder email OTP dispatch.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use DateInterval;
use ReturnTag\TagCore\Application\Auth\ActivationOtpCodeGenerator;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;

/** Issues Finder OTP plaintext only inside a background Worker. */
final readonly class DispatchFinderEmailOtp {
	/**
	 * Create the Worker use case.
	 *
	 * @param FeatureFlagReader            $flags Operational controls.
	 * @param FinderEmailVerificationStore $store OTP persistence.
	 * @param FinderEmailProtector         $protector Sensitive-value protection.
	 * @param ActivationOtpCodeGenerator   $codes In-memory code generator.
	 * @param FinderEmailOtpSender         $sender Transactional email sender.
	 * @param Clock                        $clock UTC clock.
	 */
	public function __construct(
		private FeatureFlagReader $flags,
		private FinderEmailVerificationStore $store,
		private FinderEmailProtector $protector,
		private ActivationOtpCodeGenerator $codes,
		private FinderEmailOtpSender $sender,
		private Clock $clock
	) {
	}

	/**
	 * Dispatch one internal challenge at most once.
	 *
	 * @param int $challenge_id Internal challenge identifier.
	 */
	public function execute( int $challenge_id ): void {
		if ( $challenge_id < 1 || ! $this->flags->is_enabled( FeatureFlag::FINDER_CONTACT ) || ! $this->flags->is_enabled( FeatureFlag::EMAIL_DISPATCH ) ) {
			return;
		}
		$challenge = $this->store->find_by_id( $challenge_id );
		if ( null === $challenge || FinderEmailVerification::PURPOSE !== $challenge->data->purpose || FinderEmailVerification::SUBJECT_TYPE !== $challenge->data->subject_type || 0 !== $challenge->data->send_count || null !== $challenge->data->consumed_at || ! ctype_digit( $challenge->data->subject_id ) ) {
			return;
		}
		$report_id = (int) $challenge->data->subject_id;
		$now       = $this->clock->now();
		$code      = $this->codes->generate();
		try {
			$issued = $this->store->claim_for_dispatch( $challenge_id, $this->protector->hash_code( $code ), $now->add( new DateInterval( 'PT10M' ) ), $now );
			if ( null !== $issued ) {
				$this->sender->send( $this->protector->decrypt_email( $issued->data->email_ciphertext, $report_id ), $code );
			}
		} finally {
			if ( function_exists( 'sodium_memzero' ) ) {
				sodium_memzero( $code );
			}
		}
	}
}
