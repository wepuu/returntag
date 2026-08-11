<?php
/**
 * Owner Account OTP workflow tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Auth;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Auth\AccountOtpProtector;
use ReturnTag\TagCore\Application\Auth\AccountOtpRateLimiter;
use ReturnTag\TagCore\Application\Auth\AccountOtpRequestResult;
use ReturnTag\TagCore\Application\Auth\AccountOtpScheduler;
use ReturnTag\TagCore\Application\Auth\AccountOtpStore;
use ReturnTag\TagCore\Application\Auth\ActivationOtpVerificationResult;
use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\Auth\CompleteAccountPasswordlessAuthentication;
use ReturnTag\TagCore\Application\Auth\PasswordlessAccountProvisioner;
use ReturnTag\TagCore\Application\Auth\PasswordlessAuthenticationResult;
use ReturnTag\TagCore\Application\Auth\RequestAccountOtp;
use ReturnTag\TagCore\Application\Auth\WordPressAccountEmailPolicy;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\Record\AuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewAuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Domain\Auth\ActivationOtpCode;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;

/**
 * Verifies Account purpose separation, queue payloads, and session completion.
 */
final class AccountOtpWorkflowTest extends TestCase {
	/** Requests persist the Account purpose and enqueue only the challenge ID. */
	public function test_request_uses_account_scope_and_queues_only_identifier(): void {
		$now          = new DateTimeImmutable( '2026-08-10 00:00:00', new DateTimeZone( 'UTC' ) );
		$email        = new EmailAddress( 'owner@example.test' );
		$email_lookup = LookupDigest::from_digest( str_repeat( 'a', 64 ) );
		$ip_lookup    = LookupDigest::from_digest( str_repeat( 'b', 64 ) );
		$placeholder  = OtpHash::from_password_hash( password_hash( 'placeholder', PASSWORD_DEFAULT ) );
		$protector    = $this->createMock( AccountOtpProtector::class );
		$protector->method( 'email_lookup' )->willReturn( $email_lookup );
		$protector->method( 'ip_lookup' )->willReturn( $ip_lookup );
		$protector->method( 'encrypt_email' )->willReturn( EmailCiphertext::from_encrypted_bytes( 'encrypted-account-email' ) );
		$protector->method( 'placeholder_hash' )->willReturn( $placeholder );
		$store = $this->createMock( AccountOtpStore::class );
		$store->method( 'count_recent_for_email' )->willReturn( 0 );
		$store->expects( self::once() )
			->method( 'create_replacing' )
			->with(
				self::callback(
					static fn( NewAuthChallengeRecord $record ): bool => RequestAccountOtp::PURPOSE === $record->purpose
						&& RequestAccountOtp::SUBJECT_TYPE === $record->subject_type
						&& $email_lookup->value === $record->subject_id
				)
			)
			->willReturnCallback( static fn( NewAuthChallengeRecord $record ): AuthChallengeRecord => new AuthChallengeRecord( 7, $record ) );
		$limiter = $this->createMock( AccountOtpRateLimiter::class );
		$limiter->method( 'reserve_request' )->willReturn( true );
		$scheduler = $this->createMock( AccountOtpScheduler::class );
		$scheduler->expects( self::once() )->method( 'schedule' )->with( 7 );
		$request = new RequestAccountOtp(
			$this->enabled_flags(),
			$store,
			$protector,
			$limiter,
			$scheduler,
			new WordPressAccountEmailPolicy(),
			new FixedClock( $now )
		);

		self::assertSame( AccountOtpRequestResult::ACCEPTED, $request->execute( $email, '192.0.2.8' ) );
	}

	/** Successful verification provisions without accepting a browser Owner ID. */
	public function test_verification_provisions_and_authenticates_verified_email(): void {
		$now          = new DateTimeImmutable( '2026-08-10 00:00:00', new DateTimeZone( 'UTC' ) );
		$email        = new EmailAddress( 'owner@example.test' );
		$email_lookup = LookupDigest::from_digest( str_repeat( 'a', 64 ) );
		$ip_lookup    = LookupDigest::from_digest( str_repeat( 'b', 64 ) );
		$protector    = $this->createMock( AccountOtpProtector::class );
		$protector->method( 'email_lookup' )->willReturn( $email_lookup );
		$protector->method( 'ip_lookup' )->willReturn( $ip_lookup );
		$protector->method( 'verify_code' )->willReturn( true );
		$store = $this->createMock( AccountOtpStore::class );
		$store->method( 'has_verifiable_latest' )->willReturn( true );
		$store->method( 'verify_latest' )->willReturn( ActivationOtpVerificationResult::VERIFIED );
		$limiter = $this->createMock( AccountOtpRateLimiter::class );
		$limiter->method( 'reserve_verification_ip' )->willReturn( true );
		$limiter->method( 'reserve_verification_email' )->willReturn( true );
		$accounts = $this->createMock( PasswordlessAccountProvisioner::class );
		$accounts->expects( self::once() )->method( 'provision' )->with( $email, $email_lookup, $now )->willReturn( 42 );
		$session = $this->createMock( AuthenticatedSession::class );
		$session->method( 'current_user_id' )->willReturn( null );
		$session->expects( self::once() )->method( 'authenticate' )->with( 42 );
		$authentication = new CompleteAccountPasswordlessAuthentication(
			$this->enabled_flags(),
			$store,
			$protector,
			$limiter,
			$accounts,
			$session,
			new WordPressAccountEmailPolicy(),
			new FixedClock( $now )
		);

		self::assertSame(
			PasswordlessAuthenticationResult::AUTHENTICATED,
			$authentication->execute( $email, new ActivationOtpCode( '123456' ), '192.0.2.8' )
		);
	}

	/** Build flags that enable Account and email dispatch only. */
	private function enabled_flags(): FeatureFlagReader {
		return new class() implements FeatureFlagReader {
			/**
			 * Enable only the two Account OTP controls.
			 *
			 * @param FeatureFlag $feature_flag Requested operational control.
			 */
			public function is_enabled( FeatureFlag $feature_flag ): bool {
				return in_array( $feature_flag, array( FeatureFlag::OWNER_ACCOUNT, FeatureFlag::EMAIL_DISPATCH ), true );
			}
		};
	}
}
