<?php
/**
 * Secure Reply Application workflow tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\Conversation\ConversationMessageRateLimiter;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayIdentity;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayProtector;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayScheduler;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayStore;
use ReturnTag\TagCore\Application\Conversation\ExchangeConversationLink;
use ReturnTag\TagCore\Application\Conversation\SubmitConversationMessage;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\Record\MessageRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewMessageRecord;
use ReturnTag\TagCore\Application\Persistence\Value\AccessTokenDigest;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\MessageCiphertext;
use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;

/** Verifies link-prefetch safety, fail-closed controls, and bounded submission. */
final class ConversationRelayWorkflowTest extends TestCase {
	/** Invalid bearer input must not reach the atomic exchange boundary. */
	public function test_link_exchange_rejects_invalid_token_without_store_access(): void {
		$store = $this->createMock( ConversationRelayStore::class );
		$store->expects( self::never() )->method( 'exchange_link' );
		$protector = $this->createMock( ConversationRelayProtector::class );
		$protector->expects( self::never() )->method( 'generate_token' );

		$service = new ExchangeConversationLink( $store, $protector, $this->clock( $this->now() ) );

		self::assertNull( $service->execute( 'not-a-valid-bearer' ) );
	}

	/** A valid link is exchanged for an independently rotated 30-minute session. */
	public function test_link_exchange_rotates_to_bounded_session(): void {
		$now         = $this->now();
		$raw_link    = str_repeat( 'L', 43 );
		$raw_session = str_repeat( 'S', 43 );
		$link_digest = AccessTokenDigest::from_digest( str_repeat( 'a', 64 ) );
		$session     = AccessTokenDigest::from_digest( str_repeat( 'b', 64 ) );
		$identity    = new ConversationRelayIdentity( 17, MessageSenderRole::OWNER );
		$protector   = $this->createMock( ConversationRelayProtector::class );
		$protector->method( 'generate_token' )->willReturn( $raw_session );
		$protector->method( 'token_digest' )->willReturnCallback(
			static fn( string $raw ): AccessTokenDigest => $raw_link === $raw ? $link_digest : $session
		);
		$store = $this->createMock( ConversationRelayStore::class );
		$store->expects( self::once() )->method( 'exchange_link' )->with(
			$link_digest,
			$session,
			$now,
			self::callback( static fn( DateTimeImmutable $expiry ): bool => $now->modify( '+30 minutes' )->getTimestamp() === $expiry->getTimestamp() )
		)->willReturn( $identity );

		$service = new ExchangeConversationLink( $store, $protector, $this->clock( $now ) );

		self::assertSame( $raw_session, $service->execute( $raw_link ) );
	}

	/** Finder Contact and Email Dispatch both gate mutation before token work. */
	public function test_message_submission_fails_closed_when_dispatch_is_disabled(): void {
		$flags = $this->createMock( FeatureFlagReader::class );
		$flags->method( 'is_enabled' )->willReturnCallback(
			static fn( FeatureFlag $flag ): bool => FeatureFlag::EMAIL_DISPATCH !== $flag
		);
		$store = $this->createMock( ConversationRelayStore::class );
		$store->expects( self::never() )->method( 'resolve_session' );

		$service = new SubmitConversationMessage(
			$flags,
			$store,
			$this->createMock( ConversationRelayProtector::class ),
			$this->createMock( ConversationMessageRateLimiter::class ),
			$this->createMock( ConversationRelayScheduler::class ),
			$this->clock( $this->now() )
		);

		self::assertFalse( $service->execute( str_repeat( 'S', 43 ), 'A valid private reply.', '192.0.2.10' ) );
	}

	/** HTML-like input is rejected before rate-limit or persistence side effects. */
	public function test_message_submission_rejects_html_before_reserving_budget(): void {
		$limiter = $this->createMock( ConversationMessageRateLimiter::class );
		$limiter->expects( self::never() )->method( 'reserve' );
		$service = new SubmitConversationMessage(
			$this->enabled_flags(),
			$this->createMock( ConversationRelayStore::class ),
			$this->createMock( ConversationRelayProtector::class ),
			$limiter,
			$this->createMock( ConversationRelayScheduler::class ),
			$this->clock( $this->now() )
		);

		self::assertFalse( $service->execute( str_repeat( 'S', 43 ), '<strong>Found it safely</strong>', '192.0.2.10' ) );
	}

	/** A valid human Message is encrypted, atomically appended, and scheduled by ID. */
	public function test_message_submission_encrypts_and_schedules_identifier_only(): void {
		$now            = $this->now();
		$raw_session    = str_repeat( 'S', 43 );
		$session_digest = AccessTokenDigest::from_digest( str_repeat( 'c', 64 ) );
		$lookup         = LookupDigest::from_digest( str_repeat( 'c', 64 ) );
		$peer           = LookupDigest::from_digest( str_repeat( 'd', 64 ) );
		$ciphertext     = MessageCiphertext::from_encrypted_bytes( 'encrypted-message-envelope' );
		$identity       = new ConversationRelayIdentity( 17, MessageSenderRole::FINDER );
		$record         = new MessageRecord(
			31,
			new NewMessageRecord( 17, MessageSenderRole::FINDER, $ciphertext, DeliveryStatus::QUEUED, null, null, $now )
		);
		$protector      = $this->createMock( ConversationRelayProtector::class );
		$protector->method( 'token_digest' )->with( $raw_session )->willReturn( $session_digest );
		$protector->method( 'peer_digest' )->with( '192.0.2.10' )->willReturn( $peer );
		$protector->expects( self::once() )->method( 'encrypt_message' )->with( 'Found it and can arrange return.', 17, MessageSenderRole::FINDER )->willReturn( $ciphertext );
		$limiter = $this->createMock( ConversationMessageRateLimiter::class );
		$limiter->expects( self::once() )->method( 'reserve' )->with( $lookup, $peer, 17, $now )->willReturn( true );
		$store = $this->createMock( ConversationRelayStore::class );
		$store->method( 'resolve_session' )->with( $session_digest, $now )->willReturn( $identity );
		$store->expects( self::once() )->method( 'append_human_message' )->with( $identity, $ciphertext, $now )->willReturn( $record );
		$scheduler = $this->createMock( ConversationRelayScheduler::class );
		$scheduler->expects( self::once() )->method( 'schedule' )->with( 31 );
		$service = new SubmitConversationMessage( $this->enabled_flags(), $store, $protector, $limiter, $scheduler, $this->clock( $now ) );

		self::assertTrue( $service->execute( $raw_session, "  Found it and can arrange return.\r\n", '192.0.2.10' ) );
	}

	/** Return a fixed UTC instant. */
	private function now(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-08-10 00:00:00', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Return a fixed clock.
	 *
	 * @param DateTimeImmutable $now Fixed instant.
	 */
	private function clock( DateTimeImmutable $now ): Clock {
		$clock = $this->createMock( Clock::class );
		$clock->method( 'now' )->willReturn( $now );
		return $clock;
	}

	/** Return both relay kill switches enabled. */
	private function enabled_flags(): FeatureFlagReader {
		$flags = $this->createMock( FeatureFlagReader::class );
		$flags->method( 'is_enabled' )->willReturn( true );
		return $flags;
	}
}
