<?php
/**
 * Finder email verification workflow tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\FinderReport\FinderEmailOtpScheduler;
use ReturnTag\TagCore\Application\FinderReport\FinderEmailProtector;
use ReturnTag\TagCore\Application\FinderReport\FinderEmailRateLimiter;
use ReturnTag\TagCore\Application\FinderReport\FinderEmailVerification;
use ReturnTag\TagCore\Application\FinderReport\FinderEmailVerificationResult;
use ReturnTag\TagCore\Application\FinderReport\FinderEmailVerificationStore;
use ReturnTag\TagCore\Application\Persistence\Record\AuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Record\ConversationRecord;
use ReturnTag\TagCore\Application\Persistence\Record\EventRecord;
use ReturnTag\TagCore\Application\Persistence\Record\FinderReportRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewAuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewConversationRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewFinderReportRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\ConversationRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportRepository;
use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Domain\Conversation\ConversationStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderReportStatus;

/** Verifies atomic promotion and current-Owner resolution. */
final class FinderEmailVerificationTest extends TestCase {
	/** A valid OTP creates one open Conversation for the current Owner. */
	public function test_verification_promotes_report_with_current_owner(): void {
		$now        = new DateTimeImmutable( '2026-08-10 00:00:00', new DateTimeZone( 'UTC' ) );
		$lookup     = LookupDigest::from_digest( str_repeat( 'a', 64 ) );
		$ciphertext = EmailCiphertext::from_encrypted_bytes( 'opaque-finder-email' );
		$hash       = OtpHash::from_password_hash( password_hash( 'opaque', PASSWORD_DEFAULT ) );
		$challenge  = new AuthChallengeRecord(
			14,
			new NewAuthChallengeRecord(
				FinderEmailVerification::PURPOSE,
				FinderEmailVerification::SUBJECT_TYPE,
				'31',
				$ciphertext,
				$lookup,
				$hash,
				0,
				1,
				null,
				$now->modify( '+10 minutes' ),
				null,
				null,
				$now
			)
		);
		$report     = new FinderReportRecord(
			31,
			new NewFinderReportRecord( 'A7R2W9', 42, null, FinderReportStatus::NOTIFIED, FinderEvidenceStatus::READY, null, $now, $now->modify( '+30 days' ), $now, $now )
		);

		$reports = $this->createMock( FinderReportRepository::class );
		$reports->method( 'find_by_id' )->with( 31 )->willReturn( $report );
		$reports->method( 'find_conversation_id' )->with( 31 )->willReturn( null );
		$reports->method( 'find_current_owner_id' )->with( 31 )->willReturn( 77 );
		$reports->expects( self::once() )->method( 'link_conversation' )->with( 31, 91, $now )->willReturn( true );

		$conversations = $this->createMock( ConversationRepository::class );
		$conversations->expects( self::once() )->method( 'insert' )->with(
			self::callback(
				static fn( NewConversationRecord $record ): bool => 77 === $record->owner_id_snapshot
					&& ConversationStatus::OPEN === $record->conversation_status
					&& $now === $record->finder_verified_at
			)
		)->willReturn(
			new ConversationRecord(
				91,
				new NewConversationRecord( 'A7R2W9', 77, $ciphertext, $lookup, $now, ConversationStatus::OPEN, $now->modify( '+30 days' ), $now, $now )
			)
		);

		$store = $this->createMock( FinderEmailVerificationStore::class );
		$store->expects( self::once() )->method( 'verify_latest' )->willReturnCallback(
			static function ( int $report_id, LookupDigest $email_lookup, DateTimeImmutable $at, int $attempts, callable $matches, callable $on_verified ) use ( $challenge, $hash, $lookup, $now ): AuthChallengeRecord {
				self::assertSame( 31, $report_id );
				self::assertSame( $lookup->value, $email_lookup->value );
				self::assertSame( $now, $at );
				self::assertSame( 5, $attempts );
				self::assertTrue( $matches( $hash ) );
				$on_verified( $challenge );
				return $challenge;
			}
		);

		$protector = $this->createMock( FinderEmailProtector::class );
		$protector->method( 'email_lookup' )->willReturn( $lookup );
		$protector->method( 'ip_lookup' )->willReturn( $lookup );
		$protector->method( 'verify_code' )->willReturn( true );
		$limiter = $this->createMock( FinderEmailRateLimiter::class );
		$limiter->method( 'reserve_verification' )->willReturn( true );
		$events = $this->createMock( EventRepository::class );
		$events->expects( self::once() )->method( 'append' )->with(
			self::callback(
				static fn( NewEventRecord $record ): bool => 'finder_conversation_opened' === $record->event_type
					&& 'finder_report' === $record->target_type
					&& '31' === $record->target_id
					&& 'opened' === $record->event_result
			)
		)->willReturnCallback( static fn( NewEventRecord $record ): EventRecord => new EventRecord( 101, $record ) );

		$service = new FinderEmailVerification(
			$this->enabled_flags(),
			$reports,
			$conversations,
			$events,
			$store,
			$protector,
			$limiter,
			$this->createMock( FinderEmailOtpScheduler::class ),
			$this->clock( $now )
		);

		self::assertSame( FinderEmailVerificationResult::VERIFIED, $service->verify( 31, 'finder@example.test', '123456', '192.0.2.1' ) );
	}

	/** Build enabled operational controls. */
	private function enabled_flags(): FeatureFlagReader {
		$flags = $this->createMock( FeatureFlagReader::class );
		$flags->method( 'is_enabled' )->willReturn( true );
		return $flags;
	}

	/**
	 * Build one fixed clock.
	 *
	 * @param DateTimeImmutable $now Fixed UTC time.
	 */
	private function clock( DateTimeImmutable $now ): Clock {
		return new class( $now ) implements Clock {
			/**
			 * Create one fixed clock.
			 *
			 * @param DateTimeImmutable $now Fixed UTC time.
			 */
			public function __construct( private DateTimeImmutable $now ) {
			}

			/** Return the fixed UTC time. */
			public function now(): DateTimeImmutable {
				return $this->now;
			}
		};
	}
}
