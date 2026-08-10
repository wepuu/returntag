<?php
/**
 * Optional Finder email verification workflow.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use DateInterval;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\Record\NewAuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewConversationRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\ConversationRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportRepository;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Conversation\ConversationStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderReportStatus;
use ReturnTag\TagCore\Application\Conversation\EnsureConversationAccess;

/** Requests and verifies optional Finder email without changing initial reporting. */
final readonly class FinderEmailVerification {
	public const PURPOSE       = 'finder_email_otp';
	public const SUBJECT_TYPE  = 'finder_report';
	private const MAX_ATTEMPTS = 5;

	/**
	 * Create the verification workflow.
	 *
	 * @param FeatureFlagReader             $flags Operational controls.
	 * @param FinderReportRepository        $reports Finder Report persistence.
	 * @param ConversationRepository        $conversations Conversation persistence.
	 * @param EventRepository               $events Privacy-safe audit persistence.
	 * @param FinderEmailVerificationStore  $store OTP persistence.
	 * @param FinderEmailProtector          $protector Sensitive-value protection.
	 * @param FinderEmailRateLimiter        $limiter Public abuse limiter.
	 * @param FinderEmailOtpScheduler       $scheduler Background scheduler.
	 * @param Clock                         $clock UTC clock.
	 * @param EnsureConversationAccess|null $ensure_conversation_access Secure conversation access provisioner.
	 */
	public function __construct(
		private FeatureFlagReader $flags,
		private FinderReportRepository $reports,
		private ConversationRepository $conversations,
		private EventRepository $events,
		private FinderEmailVerificationStore $store,
		private FinderEmailProtector $protector,
		private FinderEmailRateLimiter $limiter,
		private FinderEmailOtpScheduler $scheduler,
		private Clock $clock,
		private ?EnsureConversationAccess $ensure_conversation_access = null
	) {
	}

	/**
	 * Request one asynchronous email challenge.
	 *
	 * @param int    $finder_report_id Internal report identifier.
	 * @param string $email_value Untrusted email input.
	 * @param string $peer_ip Direct peer IP.
	 */
	public function request( int $finder_report_id, string $email_value, string $peer_ip ): FinderEmailVerificationResult {
		if ( ! $this->available_report( $finder_report_id ) ) {
			return FinderEmailVerificationResult::UNAVAILABLE;
		}

		try {
			$email  = new EmailAddress( $email_value );
			$lookup = $this->protector->email_lookup( $email );
			$peer   = $this->protector->ip_lookup( $peer_ip );
			$now    = $this->clock->now();
		} catch ( \Throwable ) {
			return FinderEmailVerificationResult::INVALID;
		}

		if ( ! $this->limiter->reserve_request( $lookup, $peer, $now )
			|| $this->store->count_recent_for_email( $lookup, $now->sub( new DateInterval( 'PT1H' ) ) ) >= 5
			|| $this->store->count_recent_for_report( $finder_report_id, $now->sub( new DateInterval( 'PT1H' ) ) ) >= 5 ) {
			return FinderEmailVerificationResult::UNAVAILABLE;
		}

		$challenge = $this->store->create_replacing(
			new NewAuthChallengeRecord(
				self::PURPOSE,
				self::SUBJECT_TYPE,
				(string) $finder_report_id,
				$this->protector->encrypt_email( $email, $finder_report_id ),
				$lookup,
				$this->protector->placeholder_hash(),
				0,
				0,
				$peer,
				$now->add( new DateInterval( 'PT10M' ) ),
				null,
				null,
				$now
			)
		);

		try {
			$this->scheduler->schedule( $challenge->challenge_id );
		} catch ( \Throwable ) {
			$this->store->revoke_unissued( $challenge->challenge_id, $now );
			return FinderEmailVerificationResult::UNAVAILABLE;
		}

		return FinderEmailVerificationResult::ACCEPTED;
	}

	/**
	 * Verify one code and atomically create the canonical Conversation.
	 *
	 * @param int    $finder_report_id Internal report identifier.
	 * @param string $email_value Untrusted email input.
	 * @param string $code Six-digit OTP input.
	 * @param string $peer_ip Direct peer IP.
	 */
	public function verify( int $finder_report_id, string $email_value, string $code, string $peer_ip ): FinderEmailVerificationResult {
		if ( ! $this->available_report( $finder_report_id ) || 1 !== preg_match( '/^[0-9]{6}$/D', $code ) ) {
			return FinderEmailVerificationResult::INVALID;
		}

		try {
			$email  = new EmailAddress( $email_value );
			$lookup = $this->protector->email_lookup( $email );
			$peer   = $this->protector->ip_lookup( $peer_ip );
			$now    = $this->clock->now();
			if ( ! $this->limiter->reserve_verification( $lookup, $peer, $now ) ) {
				return FinderEmailVerificationResult::UNAVAILABLE;
			}
			$challenge = $this->store->verify_latest(
				$finder_report_id,
				$lookup,
				$now,
				self::MAX_ATTEMPTS,
				fn( $hash ): bool => $this->protector->verify_code( $code, $hash ),
				function ( $verified ) use ( $finder_report_id, $lookup, $now ): void {
					if ( null !== $this->reports->find_conversation_id( $finder_report_id ) ) {
						return;
					}
					$report   = $this->reports->find_by_id( $finder_report_id );
					$owner_id = $this->reports->find_current_owner_id( $finder_report_id );
					if ( null === $report || ! in_array( $report->data->report_status, array( FinderReportStatus::RECEIVED, FinderReportStatus::PROCESSING, FinderReportStatus::READY, FinderReportStatus::NOTIFIED ), true ) ) {
						throw new \RuntimeException( 'Finder Report is unavailable.' );
					}
					if ( null === $owner_id ) {
						throw new \RuntimeException( 'Finder Report Owner is unavailable.' );
					}
					$conversation = $this->conversations->insert(
						new NewConversationRecord(
							$report->data->tag_id,
							$owner_id,
							$verified->data->email_ciphertext,
							$lookup,
							$now,
							ConversationStatus::OPEN,
							$now->add( new DateInterval( 'P30D' ) ),
							$now,
							$now
						)
					);
					if ( ! $this->reports->link_conversation( $finder_report_id, $conversation->conversation_id, $now ) ) {
						throw new \RuntimeException( 'Finder Report could not be linked.' );
					}
					$this->events->append(
						new NewEventRecord(
							'finder_conversation_opened',
							'system',
							null,
							'finder_report',
							(string) $finder_report_id,
							'opened',
							null,
							EventMetadata::none(),
							$now
						)
					);
				}
			);
		} catch ( \Throwable ) {
			return FinderEmailVerificationResult::INVALID;
		}

		if ( null === $challenge ) {
			return FinderEmailVerificationResult::INVALID;
		}

		$this->ensure_conversation_access?->execute( $finder_report_id );

		return FinderEmailVerificationResult::VERIFIED;
	}

	/**
	 * Check report and operational eligibility without leaking state.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 */
	private function available_report( int $finder_report_id ): bool {
		if ( $finder_report_id < 1 || ! $this->flags->is_enabled( FeatureFlag::FINDER_CONTACT ) || ! $this->flags->is_enabled( FeatureFlag::EMAIL_DISPATCH ) ) {
			return false;
		}
		$report = $this->reports->find_by_id( $finder_report_id );
		return null !== $report && in_array( $report->data->report_status, array( FinderReportStatus::RECEIVED, FinderReportStatus::PROCESSING, FinderReportStatus::READY, FinderReportStatus::NOTIFIED ), true );
	}
}
