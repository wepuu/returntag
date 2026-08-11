<?php
/**
 * Owner Test Email application tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Account;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Account\OwnerTestEmailRateLimiter;
use ReturnTag\TagCore\Application\Account\OwnerTestEmailDispatchClaimStore;
use ReturnTag\TagCore\Application\Account\OwnerTestEmailResult;
use ReturnTag\TagCore\Application\Account\OwnerTestEmailScheduler;
use ReturnTag\TagCore\Application\Account\OwnerTestEmailSender;
use ReturnTag\TagCore\Application\Account\RequestOwnerTestEmail;
use ReturnTag\TagCore\Application\Account\DispatchOwnerTestEmail;
use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\Auth\AuthenticatedUserEmailReader;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryEventRepository;

/** Verifies audit-first and identifier-only Test Email requests. */
final class OwnerTestEmailTest extends TestCase {
	/** Successful requests persist an Event before queueing internal IDs only. */
	public function test_request_persists_event_and_queues_only_identifiers(): void {
		$events    = new InMemoryEventRepository();
		$scheduler = $this->createMock( OwnerTestEmailScheduler::class );
		$scheduler->expects( self::once() )->method( 'schedule' )->with( 1, 42 );
		$limiter = $this->createMock( OwnerTestEmailRateLimiter::class );
		$limiter->method( 'reserve' )->willReturn( true );

		$result = ( new RequestOwnerTestEmail( $this->session(), $this->flags(), $limiter, $events, $scheduler, $this->clock() ) )->execute( '192.0.2.19' );

		self::assertSame( OwnerTestEmailResult::ACCEPTED, $result );
		self::assertCount( 1, $events->records );
		self::assertSame( 'owner_test_email_requested', $events->records[0]->data->event_type );
		self::assertSame( '42', $events->records[0]->data->target_id );
	}

	/** A throttled request neither persists an Event nor queues delivery. */
	public function test_throttled_request_has_no_side_effects(): void {
		$events    = new InMemoryEventRepository();
		$scheduler = $this->createMock( OwnerTestEmailScheduler::class );
		$scheduler->expects( self::never() )->method( 'schedule' );
		$limiter = $this->createMock( OwnerTestEmailRateLimiter::class );
		$limiter->method( 'reserve' )->willReturn( false );

		self::assertSame( OwnerTestEmailResult::THROTTLED, ( new RequestOwnerTestEmail( $this->session(), $this->flags(), $limiter, $events, $scheduler, $this->clock() ) )->execute( '192.0.2.19' ) );
		self::assertSame( array(), $events->records );
	}

	/** A claimed Worker request resolves the current address and audits mailer acceptance. */
	public function test_dispatch_claims_before_resolving_and_sending(): void {
		$events = new InMemoryEventRepository();
		$claims = $this->createMock( OwnerTestEmailDispatchClaimStore::class );
		$claims->expects( self::once() )->method( 'claim' )->with( 9 )->willReturn( true );
		$emails = $this->createMock( AuthenticatedUserEmailReader::class );
		$emails->expects( self::once() )->method( 'find' )->with( 42 )->willReturn( new EmailAddress( 'owner@example.test' ) );
		$sender = $this->createMock( OwnerTestEmailSender::class );
		$sender->expects( self::once() )->method( 'send' )->willReturn( true );

		( new DispatchOwnerTestEmail( $this->flags(), $emails, $claims, $sender, $events, $this->clock() ) )->execute( 9, 42 );

		self::assertCount( 1, $events->records );
		self::assertSame( 'owner_test_email_accepted', $events->records[0]->data->event_type );
		self::assertSame( 'accepted_by_mailer', $events->records[0]->data->event_result );
	}

	/** A duplicate Worker request stops before address resolution or mail submission. */
	public function test_duplicate_dispatch_is_a_noop(): void {
		$claims = $this->createMock( OwnerTestEmailDispatchClaimStore::class );
		$claims->method( 'claim' )->willReturn( false );
		$emails = $this->createMock( AuthenticatedUserEmailReader::class );
		$emails->expects( self::never() )->method( 'find' );
		$sender = $this->createMock( OwnerTestEmailSender::class );
		$sender->expects( self::never() )->method( 'send' );

		( new DispatchOwnerTestEmail( $this->flags(), $emails, $claims, $sender, new InMemoryEventRepository(), $this->clock() ) )->execute( 9, 42 );
	}

	/** Return one authenticated Owner session. */
	private function session(): AuthenticatedSession {
		$session = $this->createMock( AuthenticatedSession::class );
		$session->method( 'current_user_id' )->willReturn( 42 );
		return $session;
	}

	/** Enable Account and Email controls. */
	private function flags(): FeatureFlagReader {
		$flags = $this->createMock( FeatureFlagReader::class );
		$flags->method( 'is_enabled' )->willReturn( true );
		return $flags;
	}

	/** Return one stable UTC Clock. */
	private function clock(): FixedClock {
		return new FixedClock( new DateTimeImmutable( '2026-08-11 00:00:00', new DateTimeZone( 'UTC' ) ) );
	}
}
