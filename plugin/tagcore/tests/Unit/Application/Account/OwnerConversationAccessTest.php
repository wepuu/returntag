<?php
/**
 * RT-317 Stage 3 Owner Conversation application tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Account;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Account\ContinueOwnerConversation;
use ReturnTag\TagCore\Application\Account\OwnerConversationAccessState;
use ReturnTag\TagCore\Application\Account\OwnerConversationContinuationStore;
use ReturnTag\TagCore\Application\Account\OwnerConversationReader;
use ReturnTag\TagCore\Application\Account\ReadOwnerConversations;
use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayProtector;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\Value\AccessTokenDigest;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;

/** Verifies session-derived identity, containment, and bounded session issuance. */
final class OwnerConversationAccessTest extends TestCase {
	/** Disabled reads do not query persistence. */
	public function test_disabled_account_does_not_read_conversations(): void {
		$reader = $this->createMock( OwnerConversationReader::class );
		$reader->expects( self::never() )->method( 'list_for_owner' );

		$result = ( new ReadOwnerConversations(
			$this->session( 42 ),
			$this->flags( false ),
			$reader,
			$this->clock()
		) )->execute();

		self::assertSame( OwnerConversationAccessState::UNAVAILABLE, $result->state );
	}

	/** Reads derive the Owner only from the authenticated WordPress session. */
	public function test_read_uses_authenticated_owner_and_current_time(): void {
		$reader = $this->createMock( OwnerConversationReader::class );
		$reader->expects( self::once() )
			->method( 'list_for_owner' )
			->with( 42, $this->now() )
			->willReturn( array() );

		$result = ( new ReadOwnerConversations(
			$this->session( 42 ),
			$this->flags( true ),
			$reader,
			$this->clock()
		) )->execute();

		self::assertSame( OwnerConversationAccessState::READY, $result->state );
		self::assertSame( array(), $result->items );
	}

	/** Continuation persists only a digest with exact current-Owner and 30-minute expiry. */
	public function test_continue_issues_role_bound_thirty_minute_session(): void {
		$raw       = str_repeat( 'A', 43 );
		$digest    = AccessTokenDigest::from_digest( str_repeat( 'b', 64 ) );
		$store     = $this->createMock( OwnerConversationContinuationStore::class );
		$protector = $this->createMock( ConversationRelayProtector::class );
		$protector->expects( self::once() )->method( 'generate_token' )->willReturn( $raw );
		$protector->expects( self::once() )->method( 'token_digest' )->with( $raw )->willReturn( $digest );
		$store->expects( self::once() )
			->method( 'issue_owner_session' )
			->with( 17, 42, $digest, $this->now()->modify( '+30 minutes' ), $this->now() )
			->willReturn( true );

		$result = ( new ContinueOwnerConversation(
			$this->session( 42 ),
			$this->flags( true ),
			$store,
			$protector,
			$this->clock()
		) )->execute( 17 );

		self::assertTrue( $result->continued );
		self::assertSame( $raw, $result->session );
	}

	/** Missing authentication fails before token generation or persistence. */
	public function test_continue_without_authenticated_owner_fails_closed(): void {
		$store     = $this->createMock( OwnerConversationContinuationStore::class );
		$protector = $this->createMock( ConversationRelayProtector::class );
		$store->expects( self::never() )->method( 'issue_owner_session' );
		$protector->expects( self::never() )->method( 'generate_token' );

		$result = ( new ContinueOwnerConversation(
			$this->session( null ),
			$this->flags( true ),
			$store,
			$protector,
			$this->clock()
		) )->execute( 17 );

		self::assertFalse( $result->continued );
		self::assertNull( $result->session );
	}

	/**
	 * Return one synthetic authenticated session.
	 *
	 * @param int|null $owner_id Synthetic Owner identifier.
	 */
	private function session( ?int $owner_id ): AuthenticatedSession {
		$session = $this->createMock( AuthenticatedSession::class );
		$session->method( 'current_user_id' )->willReturn( $owner_id );

		return $session;
	}

	/**
	 * Return one fixed Account flag reader.
	 *
	 * @param bool $enabled Whether the Account control is enabled.
	 */
	private function flags( bool $enabled ): FeatureFlagReader {
		return new class( $enabled ) implements FeatureFlagReader {
			/**
			 * Create the fixed reader.
			 *
			 * @param bool $enabled Whether the Account control is enabled.
			 */
			public function __construct( private readonly bool $enabled ) {
			}

			/**
			 * Read the synthetic control.
			 *
			 * @param FeatureFlag $feature_flag Requested control.
			 */
			public function is_enabled( FeatureFlag $feature_flag ): bool {
				return FeatureFlag::OWNER_ACCOUNT === $feature_flag && $this->enabled;
			}
		};
	}

	/** Return the fixed UTC clock. */
	private function clock(): FixedClock {
		return new FixedClock( $this->now() );
	}

	/** Return the fixed UTC instant. */
	private function now(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-08-11 12:00:00', new DateTimeZone( 'UTC' ) );
	}
}
