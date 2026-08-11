<?php
/**
 * Owner lifecycle application tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Account;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Account\ManageOwnerLifecycle;
use ReturnTag\TagCore\Application\Account\OwnerLifecycleResult;
use ReturnTag\TagCore\Application\Account\OwnerLifecycleStore;
use ReturnTag\TagCore\Application\Account\OwnerTransferScheduler;
use ReturnTag\TagCore\Application\Auth\AccountOtpProtector;
use ReturnTag\TagCore\Application\Auth\AccountOtpRateLimiter;
use ReturnTag\TagCore\Application\Auth\AccountOtpStore;
use ReturnTag\TagCore\Application\Auth\ActivationOtpVerificationResult;
use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\Auth\AuthenticatedUserEmailReader;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Domain\Auth\ActivationOtpCode;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;

/** Verifies reauthentication and identifier-only transfer scheduling. */
final class OwnerLifecycleTest extends TestCase {
	/** A verified current Owner may create and queue one encrypted Transfer. */
	public function test_transfer_requires_fresh_otp_and_queues_only_transfer_id(): void {
		$store = $this->createMock( OwnerLifecycleStore::class );
		$store->expects( self::once() )->method( 'create_transfer' )
			->with(
				self::isInstanceOf( TagId::class ),
				42,
				self::isInstanceOf( EmailCiphertext::class ),
				self::isInstanceOf( LookupDigest::class ),
				self::isInstanceOf( DateTimeImmutable::class ),
				self::isInstanceOf( DateTimeImmutable::class )
			)
			->willReturn( 73 );
		$scheduler = $this->createMock( OwnerTransferScheduler::class );
		$scheduler->expects( self::once() )->method( 'schedule' )->with( 73 );

		$service = $this->service( $store, $scheduler, ActivationOtpVerificationResult::VERIFIED );

		self::assertSame(
			OwnerLifecycleResult::CREATED,
			$service->transfer( TagId::from_canonical( 'AAA234' ), new EmailAddress( 'new-owner@example.test' ), new ActivationOtpCode( '123456' ), '192.0.2.18' )
		);
	}

	/** A failed OTP must not mutate or enqueue a Transfer. */
	public function test_transfer_rejects_failed_reauthentication(): void {
		$store = $this->createMock( OwnerLifecycleStore::class );
		$store->expects( self::never() )->method( 'create_transfer' );
		$scheduler = $this->createMock( OwnerTransferScheduler::class );
		$scheduler->expects( self::never() )->method( 'schedule' );

		self::assertSame(
			OwnerLifecycleResult::AUTHENTICATION_REQUIRED,
			$this->service( $store, $scheduler, ActivationOtpVerificationResult::INVALID )->transfer( TagId::from_canonical( 'AAA234' ), new EmailAddress( 'new-owner@example.test' ), new ActivationOtpCode( '123456' ), '192.0.2.18' )
		);
	}

	/** Retirement requires the exact canonical Tag ID before OTP consumption. */
	public function test_retire_rejects_mismatched_confirmation_before_store_call(): void {
		$store = $this->createMock( OwnerLifecycleStore::class );
		$store->expects( self::never() )->method( 'retire' );

		self::assertSame(
			OwnerLifecycleResult::UNAVAILABLE,
			$this->service( $store, $this->createMock( OwnerTransferScheduler::class ), ActivationOtpVerificationResult::VERIFIED )->retire( TagId::from_canonical( 'AAA234' ), 'BBB234', new ActivationOtpCode( '123456' ), '192.0.2.18' )
		);
	}

	/** Cancellation is current-session and feature-gated without consuming OTP. */
	public function test_cancel_uses_current_owner_identifier(): void {
		$store = $this->createMock( OwnerLifecycleStore::class );
		$store->expects( self::once() )->method( 'cancel_transfer' )->with( self::isInstanceOf( TagId::class ), 42, self::isInstanceOf( DateTimeImmutable::class ) )->willReturn( OwnerLifecycleResult::CANCELLED );

		self::assertSame(
			OwnerLifecycleResult::CANCELLED,
			$this->service( $store, $this->createMock( OwnerTransferScheduler::class ), ActivationOtpVerificationResult::VERIFIED )->cancel( TagId::from_canonical( 'AAA234' ) )
		);
	}

	/**
	 * Build a fully enabled service with controlled OTP verification.
	 *
	 * @param OwnerLifecycleStore             $store Lifecycle persistence mock.
	 * @param OwnerTransferScheduler          $scheduler Transfer queue mock.
	 * @param ActivationOtpVerificationResult $verification Controlled OTP result.
	 */
	private function service( OwnerLifecycleStore $store, OwnerTransferScheduler $scheduler, ActivationOtpVerificationResult $verification ): ManageOwnerLifecycle {
		$session = $this->createMock( AuthenticatedSession::class );
		$session->method( 'current_user_id' )->willReturn( 42 );
		$emails = $this->createMock( AuthenticatedUserEmailReader::class );
		$emails->method( 'find' )->willReturn( new EmailAddress( 'owner@example.test' ) );
		$flags = $this->createMock( FeatureFlagReader::class );
		$flags->method( 'is_enabled' )->willReturn( true );
		$lookup    = LookupDigest::from_digest( str_repeat( 'a', 64 ) );
		$protector = $this->createMock( AccountOtpProtector::class );
		$protector->method( 'email_lookup' )->willReturn( $lookup );
		$protector->method( 'ip_lookup' )->willReturn( LookupDigest::from_digest( str_repeat( 'b', 64 ) ) );
		$protector->method( 'encrypt_email' )->willReturn( EmailCiphertext::from_encrypted_bytes( 'encrypted-target-email' ) );
		$protector->method( 'verify_code' )->willReturn( ActivationOtpVerificationResult::VERIFIED === $verification );
		$otp = $this->createMock( AccountOtpStore::class );
		$otp->method( 'has_verifiable_latest' )->willReturn( true );
		$otp->method( 'verify_latest' )->willReturn( $verification );
		$limiter = $this->createMock( AccountOtpRateLimiter::class );
		$limiter->method( 'reserve_verification_ip' )->willReturn( true );
		$limiter->method( 'reserve_verification_email' )->willReturn( true );

		return new ManageOwnerLifecycle( $session, $emails, $flags, $otp, $protector, $limiter, $store, $scheduler, new FixedClock( new DateTimeImmutable( '2026-08-11 00:00:00', new DateTimeZone( 'UTC' ) ) ) );
	}
}
