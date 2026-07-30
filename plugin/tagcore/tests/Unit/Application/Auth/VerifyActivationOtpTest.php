<?php
/**
 * Activation OTP verification use-case tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Auth;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Auth\ActivationOtpVerificationRateLimiter;
use ReturnTag\TagCore\Application\Auth\ActivationOtpVerificationResult;
use ReturnTag\TagCore\Application\Auth\ActivationOtpVerificationStore;
use ReturnTag\TagCore\Application\Auth\VerifyActivationOtp;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPagePolicy;
use ReturnTag\TagCore\Application\PublicTag\PublicTagStateReader;
use ReturnTag\TagCore\Application\PublicTag\PublicTagStateRecord;
use ReturnTag\TagCore\Application\PublicTag\ResolvePublicTagPage;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailabilityPolicy;
use ReturnTag\TagCore\Domain\Auth\ActivationOtpCode;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Security\ActivationOtpSecrets;
use ReturnTag\TagCore\Infrastructure\Security\SodiumActivationOtpProtector;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;

/**
 * Verifies eligibility, throttling, and secure comparison coordination.
 */
final class VerifyActivationOtpTest extends TestCase {
	/**
	 * Eligible attempts reserve a budget and delegate one atomic comparison.
	 */
	public function test_verifies_an_eligible_matching_code(): void {
		$now       = new DateTimeImmutable( '2026-07-30 00:00:00', new DateTimeZone( 'UTC' ) );
		$flags     = $this->enabled_flags();
		$protector = $this->protector();
		$tag_id    = TagId::from_canonical( 'A7R2W9' );
		$email     = new EmailAddress( 'owner@example.test' );
		$hash      = $protector->hash_code( '123456' );
		$limiter   = $this->createMock( ActivationOtpVerificationRateLimiter::class );
		$store     = $this->createMock( ActivationOtpVerificationStore::class );

		$limiter->expects( self::once() )
			->method( 'reserve_public' )
			->willReturn( true );
		$limiter->expects( self::once() )
			->method( 'reserve_email' )
			->willReturn( true );
		$store->expects( self::once() )
			->method( 'has_verifiable_latest' )
			->with(
				self::callback( static fn( TagId $candidate ): bool => $candidate->value === $tag_id->value ),
				self::callback(
					static fn( LookupDigest $candidate ): bool => $candidate->value === $protector->email_lookup( $email )->value
				),
				$now,
				5
			)
			->willReturn( true );
		$store->expects( self::once() )
			->method( 'verify_latest' )
			->willReturnCallback(
				static function (
					TagId $stored_tag_id,
					LookupDigest $email_lookup,
					DateTimeImmutable $verified_at,
					int $maximum_attempts,
					callable $matches
				) use (
					$tag_id,
					$email,
					$now,
					$protector,
					$hash
				): ActivationOtpVerificationResult {
					self::assertSame( $tag_id->value, $stored_tag_id->value );
					self::assertSame( $protector->email_lookup( $email )->value, $email_lookup->value );
					self::assertSame( $now, $verified_at );
					self::assertSame( 5, $maximum_attempts );
					self::assertTrue( $matches( $hash ) );

					return ActivationOtpVerificationResult::VERIFIED;
				}
			);

		$verification = new VerifyActivationOtp(
			$this->activation_pages( $flags ),
			$flags,
			$store,
			$protector,
			$limiter,
			new FixedClock( $now )
		);

		self::assertSame(
			ActivationOtpVerificationResult::VERIFIED,
			$verification->execute( $tag_id, $email, new ActivationOtpCode( '123456' ), '192.0.2.4' )
		);
	}

	/**
	 * A rejected verification budget never reaches challenge persistence.
	 */
	public function test_throttling_stops_before_code_comparison(): void {
		$now     = new DateTimeImmutable( '2026-07-30 00:00:00', new DateTimeZone( 'UTC' ) );
		$flags   = $this->enabled_flags();
		$limiter = $this->createMock( ActivationOtpVerificationRateLimiter::class );
		$store   = $this->createMock( ActivationOtpVerificationStore::class );

		$limiter->method( 'reserve_public' )->willReturn( false );
		$limiter->expects( self::never() )->method( 'reserve_email' );
		$store->expects( self::never() )->method( 'has_verifiable_latest' );
		$store->expects( self::never() )->method( 'verify_latest' );

		$verification = new VerifyActivationOtp(
			$this->activation_pages( $flags ),
			$flags,
			$store,
			$this->protector(),
			$limiter,
			new FixedClock( $now )
		);

		self::assertSame(
			ActivationOtpVerificationResult::THROTTLED,
			$verification->execute(
				TagId::from_canonical( 'A7R2W9' ),
				new EmailAddress( 'owner@example.test' ),
				new ActivationOtpCode( '123456' ),
				'192.0.2.4'
			)
		);
	}

	/**
	 * Unknown challenge identities never allocate durable email buckets.
	 */
	public function test_missing_challenge_stops_before_email_reservation(): void {
		$now     = new DateTimeImmutable( '2026-07-30 00:00:00', new DateTimeZone( 'UTC' ) );
		$flags   = $this->enabled_flags();
		$limiter = $this->createMock( ActivationOtpVerificationRateLimiter::class );
		$store   = $this->createMock( ActivationOtpVerificationStore::class );

		$limiter->expects( self::once() )->method( 'reserve_public' )->willReturn( true );
		$limiter->expects( self::never() )->method( 'reserve_email' );
		$store->expects( self::once() )->method( 'has_verifiable_latest' )->willReturn( false );
		$store->expects( self::never() )->method( 'verify_latest' );

		$verification = new VerifyActivationOtp(
			$this->activation_pages( $flags ),
			$flags,
			$store,
			$this->protector(),
			$limiter,
			new FixedClock( $now )
		);

		self::assertSame(
			ActivationOtpVerificationResult::INVALID,
			$verification->execute(
				TagId::from_canonical( 'A7R2W9' ),
				new EmailAddress( 'unknown@example.test' ),
				new ActivationOtpCode( '123456' ),
				'192.0.2.4'
			)
		);
	}

	/**
	 * Email throttling occurs only after challenge eligibility is established.
	 */
	public function test_email_throttling_stops_before_code_comparison(): void {
		$now     = new DateTimeImmutable( '2026-07-30 00:00:00', new DateTimeZone( 'UTC' ) );
		$flags   = $this->enabled_flags();
		$limiter = $this->createMock( ActivationOtpVerificationRateLimiter::class );
		$store   = $this->createMock( ActivationOtpVerificationStore::class );

		$limiter->expects( self::once() )->method( 'reserve_public' )->willReturn( true );
		$store->expects( self::once() )->method( 'has_verifiable_latest' )->willReturn( true );
		$limiter->expects( self::once() )->method( 'reserve_email' )->willReturn( false );
		$store->expects( self::never() )->method( 'verify_latest' );

		$verification = new VerifyActivationOtp(
			$this->activation_pages( $flags ),
			$flags,
			$store,
			$this->protector(),
			$limiter,
			new FixedClock( $now )
		);

		self::assertSame(
			ActivationOtpVerificationResult::THROTTLED,
			$verification->execute(
				TagId::from_canonical( 'A7R2W9' ),
				new EmailAddress( 'owner@example.test' ),
				new ActivationOtpCode( '123456' ),
				'192.0.2.4'
			)
		);
	}

	/**
	 * Build enabled feature flags.
	 */
	private function enabled_flags(): FeatureFlagReader {
		return new class() implements FeatureFlagReader {
			/**
			 * Enable every requested flag.
			 *
			 * @param FeatureFlag $feature_flag Requested flag.
			 */
			public function is_enabled( FeatureFlag $feature_flag ): bool {
				unset( $feature_flag );
				return true;
			}
		};
	}

	/**
	 * Build an eligible activation state resolver.
	 *
	 * @param FeatureFlagReader $flags Enabled flags.
	 */
	private function activation_pages( FeatureFlagReader $flags ): ResolvePublicTagPage {
		return new ResolvePublicTagPage(
			new class() implements PublicTagStateReader {
				/**
				 * Return one eligible synthetic Tag.
				 *
				 * @param TagId $tag_id Requested Tag.
				 */
				public function find( TagId $tag_id ): ?PublicTagStateRecord {
					unset( $tag_id );

					return new PublicTagStateRecord(
						null,
						TagType::CLASSIC_TAG,
						null,
						TagStatus::UNREGISTERED,
						false,
						null,
						null,
						BatchStatus::RELEASED,
						true
					);
				}
			},
			$flags,
			new PublicTagPagePolicy( new TagActivationAvailabilityPolicy() )
		);
	}

	/**
	 * Build deterministic test-only crypto.
	 */
	private function protector(): SodiumActivationOtpProtector {
		return new SodiumActivationOtpProtector(
			ActivationOtpSecrets::from_keys(
				str_repeat( 'e', 32 ),
				str_repeat( 'l', 32 ),
				str_repeat( 'p', 32 )
			)
		);
	}
}
