<?php
/**
 * Participant Conversation safety Application tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\Conversation\ApplyConversationSafetyAction;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayIdentity;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayProtector;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayStore;
use ReturnTag\TagCore\Application\Conversation\ConversationSafetyAction;
use ReturnTag\TagCore\Application\Persistence\Value\AccessTokenDigest;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;

/** Verifies role separation and privacy-safe fail-closed behavior. */
final class ConversationSafetyActionTest extends TestCase {
	/** Invalid session input cannot reach persistence or cryptography. */
	public function test_invalid_session_fails_before_store_access(): void {
		$store = $this->createMock( ConversationRelayStore::class );
		$store->expects( self::never() )->method( 'resolve_session' );
		$protector = $this->createMock( ConversationRelayProtector::class );
		$protector->expects( self::never() )->method( 'token_digest' );

		$service = new ApplyConversationSafetyAction( $store, $protector, $this->clock() );

		self::assertFalse( $service->execute( 'invalid', ConversationSafetyAction::FINDER_CLOSE ) );
	}

	/** An Owner session cannot invoke the Finder-only close action. */
	public function test_role_mismatch_fails_before_terminal_mutation(): void {
		$session   = str_repeat( 'S', 43 );
		$digest    = AccessTokenDigest::from_digest( str_repeat( 'a', 64 ) );
		$store     = $this->createMock( ConversationRelayStore::class );
		$protector = $this->createMock( ConversationRelayProtector::class );
		$protector->method( 'token_digest' )->with( $session )->willReturn( $digest );
		$store->method( 'resolve_session' )->with( $digest, $this->now() )->willReturn( new ConversationRelayIdentity( 17, MessageSenderRole::OWNER ) );
		$store->expects( self::never() )->method( 'apply_safety_action' );

		$service = new ApplyConversationSafetyAction( $store, $protector, $this->clock() );

		self::assertFalse( $service->execute( $session, ConversationSafetyAction::FINDER_CLOSE ) );
	}

	/** The matching current role delegates exactly one closed action. */
	public function test_matching_role_delegates_atomic_action(): void {
		$session   = str_repeat( 'S', 43 );
		$digest    = AccessTokenDigest::from_digest( str_repeat( 'a', 64 ) );
		$identity  = new ConversationRelayIdentity( 17, MessageSenderRole::OWNER );
		$store     = $this->createMock( ConversationRelayStore::class );
		$protector = $this->createMock( ConversationRelayProtector::class );
		$protector->method( 'token_digest' )->with( $session )->willReturn( $digest );
		$store->method( 'resolve_session' )->with( $digest, $this->now() )->willReturn( $identity );
		$store->expects( self::once() )->method( 'apply_safety_action' )->with( $identity, ConversationSafetyAction::OWNER_REPORT_BLOCK, $this->now() )->willReturn( true );

		$service = new ApplyConversationSafetyAction( $store, $protector, $this->clock() );

		self::assertTrue( $service->execute( $session, ConversationSafetyAction::OWNER_REPORT_BLOCK ) );
	}

	/** Return the fixed test instant. */
	private function now(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-08-10 00:00:00', new DateTimeZone( 'UTC' ) );
	}

	/** Return a fixed clock. */
	private function clock(): Clock {
		$clock = $this->createMock( Clock::class );
		$clock->method( 'now' )->willReturn( $this->now() );
		return $clock;
	}
}
