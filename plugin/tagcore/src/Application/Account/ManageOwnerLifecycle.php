<?php
/**
 * High-risk Owner lifecycle use cases.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use DateInterval;
use ReturnTag\TagCore\Application\Auth\AccountOtpProtector;
use ReturnTag\TagCore\Application\Auth\AccountOtpRateLimiter;
use ReturnTag\TagCore\Application\Auth\AccountOtpStore;
use ReturnTag\TagCore\Application\Auth\ActivationOtpVerificationResult;
use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\Auth\AuthenticatedUserEmailReader;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Domain\Auth\ActivationOtpCode;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Tag\TagId;
use Throwable;

/** Applies owner reauthentication before high-risk lifecycle mutations. */
final readonly class ManageOwnerLifecycle {
	/**
	 * Create the lifecycle service.
	 *
	 * @param AuthenticatedSession         $session Current WordPress session.
	 * @param AuthenticatedUserEmailReader $emails Server-side email reader.
	 * @param FeatureFlagReader            $flags Operational controls.
	 * @param AccountOtpStore              $otp Account OTP persistence.
	 * @param AccountOtpProtector          $protector Sensitive-data protection.
	 * @param AccountOtpRateLimiter        $limiter OTP verification budget.
	 * @param OwnerLifecycleStore          $store Atomic lifecycle persistence.
	 * @param OwnerTransferScheduler       $scheduler Identifier-only queue.
	 * @param Clock                        $clock UTC clock.
	 */
	public function __construct( private AuthenticatedSession $session, private AuthenticatedUserEmailReader $emails, private FeatureFlagReader $flags, private AccountOtpStore $otp, private AccountOtpProtector $protector, private AccountOtpRateLimiter $limiter, private OwnerLifecycleStore $store, private OwnerTransferScheduler $scheduler, private Clock $clock ) {}

	/**
	 * Start one reauthenticated pending Transfer.
	 *
	 * @param TagId             $tag_id Candidate current-Owner Tag.
	 * @param EmailAddress      $target Validated target email.
	 * @param ActivationOtpCode $code Fresh Account OTP.
	 * @param string            $ip Direct-peer IP address.
	 */
	public function transfer( TagId $tag_id, EmailAddress $target, ActivationOtpCode $code, string $ip ): OwnerLifecycleResult {
		$owner_id = $this->reauthenticate( $code, $ip );
		if ( null === $owner_id ) {
			return OwnerLifecycleResult::AUTHENTICATION_REQUIRED; }
		$lookup = $this->protector->email_lookup( $target );
		$now    = $this->clock->now();
		$id     = $this->store->create_transfer( $tag_id, $owner_id, $this->protector->encrypt_email( $target, $lookup ), $lookup, $now->add( new DateInterval( 'P1D' ) ), $now );
		if ( null === $id ) {
			return OwnerLifecycleResult::UNAVAILABLE; }
		try {
			$this->scheduler->schedule( $id );
		} catch ( Throwable ) {
			return OwnerLifecycleResult::UNAVAILABLE; }
		return OwnerLifecycleResult::CREATED;
	}

	/**
	 * Permanently retire one exact, reauthenticated Owner Tag.
	 *
	 * @param TagId             $tag_id Candidate current-Owner Tag.
	 * @param string            $confirmation Exact canonical Tag ID confirmation.
	 * @param ActivationOtpCode $code Fresh Account OTP.
	 * @param string            $ip Direct-peer IP address.
	 */
	public function retire( TagId $tag_id, string $confirmation, ActivationOtpCode $code, string $ip ): OwnerLifecycleResult {
		if ( $confirmation !== $tag_id->value ) {
			return OwnerLifecycleResult::UNAVAILABLE; }
		$owner_id = $this->reauthenticate( $code, $ip );
		return null === $owner_id ? OwnerLifecycleResult::AUTHENTICATION_REQUIRED : $this->store->retire( $tag_id, $owner_id, $this->clock->now() );
	}

	/**
	 * Cancel pending invitations for one current-Owner Tag.
	 *
	 * @param TagId $tag_id Current Owner Tag.
	 */
	public function cancel( TagId $tag_id ): OwnerLifecycleResult {
		$owner_id = $this->session->current_user_id();
		if ( null === $owner_id || ! $this->flags->is_enabled( FeatureFlag::OWNER_ACCOUNT ) || ! $this->flags->is_enabled( FeatureFlag::OWNER_LIFECYCLE ) ) {
			return OwnerLifecycleResult::UNAVAILABLE;
		}
		return $this->store->cancel_transfer( $tag_id, $owner_id, $this->clock->now() );
	}

	/**
	 * Verify a fresh Account OTP and return the current Owner identifier.
	 *
	 * @param ActivationOtpCode $code Fresh Account OTP.
	 * @param string            $ip Direct-peer IP address.
	 */
	private function reauthenticate( ActivationOtpCode $code, string $ip ): ?int {
		$owner_id = $this->session->current_user_id();
		if ( null === $owner_id || ! $this->flags->is_enabled( FeatureFlag::OWNER_ACCOUNT ) || ! $this->flags->is_enabled( FeatureFlag::OWNER_LIFECYCLE ) ) {
			return null; }
		$email = $this->emails->find( $owner_id );
		if ( null === $email ) {
			return null; }
		$now          = $this->clock->now();
		$email_lookup = $this->protector->email_lookup( $email );
		$ip_lookup    = $this->protector->ip_lookup( $ip );
		if ( ! $this->limiter->reserve_verification_ip( $ip_lookup, $now ) || ! $this->otp->has_verifiable_latest( $email_lookup, $now, 5 ) || ! $this->limiter->reserve_verification_email( $email_lookup, $now ) ) {
			return null; }
		return ActivationOtpVerificationResult::VERIFIED === $this->otp->verify_latest( $email_lookup, $now, 5, fn( \ReturnTag\TagCore\Application\Persistence\Value\OtpHash $hash ): bool => $this->protector->verify_code( $code->value, $hash ) ) ? $owner_id : null;
	}
}
