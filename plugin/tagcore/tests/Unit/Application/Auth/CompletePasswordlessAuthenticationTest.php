<?php
/**
 * Complete passwordless authentication tests.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Auth;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Auth\ActivationOtpProtector;
use ReturnTag\TagCore\Application\Auth\ActivationOtpVerificationResult;
use ReturnTag\TagCore\Application\Auth\ActivationOtpVerifier;
use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\Auth\CompletePasswordlessAuthentication;
use ReturnTag\TagCore\Application\Auth\PasswordlessAccountProvisioner;
use ReturnTag\TagCore\Application\Auth\PasswordlessAuthenticationResult;
use ReturnTag\TagCore\Application\Auth\WordPressAccountEmailPolicy;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Domain\Auth\ActivationOtpCode;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;
use RuntimeException;

/**
 * Verifies ordering across OTP, account, and session boundaries.
 */
final class CompletePasswordlessAuthenticationTest extends TestCase {
	/**
	 * An existing session stops before OTP verification.
	 */
	public function test_existing_session_does_not_consume_otp_or_switch_accounts(): void {
		$verifier = $this->createMock( ActivationOtpVerifier::class );
		$accounts = $this->createMock( PasswordlessAccountProvisioner::class );
		$session  = $this->createMock( AuthenticatedSession::class );

		$session->method( 'current_user_id' )->willReturn( 42 );
		$verifier->expects( self::never() )->method( 'execute' );
		$accounts->expects( self::never() )->method( 'provision' );
		$session->expects( self::never() )->method( 'authenticate' );

		self::assertSame(
			PasswordlessAuthenticationResult::ALREADY_AUTHENTICATED,
			$this->service( $verifier, $accounts, $session )->execute(
				TagId::from_canonical( 'A7R2W9' ),
				new EmailAddress( 'different@example.test' ),
				new ActivationOtpCode( '123456' ),
				'192.0.2.4'
			)
		);
	}

	/**
	 * WordPress-incompatible email stops before consuming the OTP.
	 */
	public function test_long_wordpress_email_stops_before_verification(): void {
		$verifier = $this->createMock( ActivationOtpVerifier::class );
		$accounts = $this->createMock( PasswordlessAccountProvisioner::class );
		$session  = $this->createMock( AuthenticatedSession::class );
		$email    = new EmailAddress( str_repeat( 'a', 64 ) . '@' . str_repeat( 'b', 30 ) . '.example.test' );

		$session->method( 'current_user_id' )->willReturn( null );
		$verifier->expects( self::never() )->method( 'execute' );
		$accounts->expects( self::never() )->method( 'provision' );

		self::assertSame(
			PasswordlessAuthenticationResult::INVALID,
			$this->service( $verifier, $accounts, $session )->execute(
				TagId::from_canonical( 'A7R2W9' ),
				$email,
				new ActivationOtpCode( '123456' ),
				'192.0.2.4'
			)
		);
	}

	/**
	 * Invalid verification creates neither an account nor a session.
	 */
	public function test_invalid_otp_creates_no_account_or_session(): void {
		$verifier = $this->createMock( ActivationOtpVerifier::class );
		$accounts = $this->createMock( PasswordlessAccountProvisioner::class );
		$session  = $this->createMock( AuthenticatedSession::class );

		$session->method( 'current_user_id' )->willReturn( null );
		$verifier->method( 'execute' )->willReturn( ActivationOtpVerificationResult::INVALID );
		$accounts->expects( self::never() )->method( 'provision' );
		$session->expects( self::never() )->method( 'authenticate' );

		self::assertSame(
			PasswordlessAuthenticationResult::INVALID,
			$this->service( $verifier, $accounts, $session )->execute(
				TagId::from_canonical( 'A7R2W9' ),
				new EmailAddress( 'owner@example.test' ),
				new ActivationOtpCode( '654321' ),
				'192.0.2.4'
			)
		);
	}

	/**
	 * A verified identity provisions once and receives a fresh session.
	 */
	public function test_verified_identity_is_provisioned_and_authenticated(): void {
		$now       = new DateTimeImmutable( '2026-07-30 10:00:00', new DateTimeZone( 'UTC' ) );
		$verifier  = $this->createMock( ActivationOtpVerifier::class );
		$accounts  = $this->createMock( PasswordlessAccountProvisioner::class );
		$session   = $this->createMock( AuthenticatedSession::class );
		$protector = $this->protector();
		$email     = new EmailAddress( 'owner@example.test' );

		$session->method( 'current_user_id' )->willReturn( null );
		$verifier->method( 'execute' )->willReturn( ActivationOtpVerificationResult::VERIFIED );
		$accounts->expects( self::once() )
			->method( 'provision' )
			->with( $email, $protector->email_lookup( $email ), $now )
			->willReturn( 77 );
		$session->expects( self::once() )->method( 'authenticate' )->with( 77 );

		$service = new CompletePasswordlessAuthentication(
			$verifier,
			$protector,
			$accounts,
			$session,
			new WordPressAccountEmailPolicy(),
			new FixedClock( $now )
		);

		self::assertSame(
			PasswordlessAuthenticationResult::AUTHENTICATED,
			$service->execute(
				TagId::from_canonical( 'A7R2W9' ),
				$email,
				new ActivationOtpCode( '123456' ),
				'192.0.2.4'
			)
		);
	}

	/**
	 * Provisioning failure cannot issue a partial authenticated session.
	 */
	public function test_provisioning_failure_does_not_create_session(): void {
		$verifier = $this->createMock( ActivationOtpVerifier::class );
		$accounts = $this->createMock( PasswordlessAccountProvisioner::class );
		$session  = $this->createMock( AuthenticatedSession::class );

		$session->method( 'current_user_id' )->willReturn( null );
		$verifier->method( 'execute' )->willReturn( ActivationOtpVerificationResult::VERIFIED );
		$accounts->method( 'provision' )->willThrowException( new RuntimeException( 'failed' ) );
		$session->expects( self::never() )->method( 'authenticate' );

		$this->expectException( RuntimeException::class );
		$this->service( $verifier, $accounts, $session )->execute(
			TagId::from_canonical( 'A7R2W9' ),
			new EmailAddress( 'owner@example.test' ),
			new ActivationOtpCode( '123456' ),
			'192.0.2.4'
		);
	}

	/**
	 * Build the use case with a deterministic keyed lookup.
	 *
	 * @param ActivationOtpVerifier          $verifier OTP verifier double.
	 * @param PasswordlessAccountProvisioner $accounts Account provisioner double.
	 * @param AuthenticatedSession           $session Session double.
	 */
	private function service(
		ActivationOtpVerifier $verifier,
		PasswordlessAccountProvisioner $accounts,
		AuthenticatedSession $session
	): CompletePasswordlessAuthentication {
		return new CompletePasswordlessAuthentication(
			$verifier,
			$this->protector(),
			$accounts,
			$session,
			new WordPressAccountEmailPolicy(),
			new FixedClock( new DateTimeImmutable( '2026-07-30 10:00:00', new DateTimeZone( 'UTC' ) ) )
		);
	}

	/**
	 * Return the small protector behavior needed by this use case.
	 */
	private function protector(): ActivationOtpProtector {
		$protector = $this->createMock( ActivationOtpProtector::class );
		$protector->method( 'email_lookup' )
			->willReturn( LookupDigest::from_digest( str_repeat( 'a', 64 ) ) );

		return $protector;
	}
}
