<?php
/**
 * RT-317 Stage 3 Conversation browser boundary tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use ReturnTag\TagCore\Account\AccountConversationFeedback;
use ReturnTag\TagCore\Account\AccountConversationFormHandler;
use ReturnTag\TagCore\Account\AccountFormRequestGuard;
use ReturnTag\TagCore\Account\AccountFormResult;
use ReturnTag\TagCore\Account\AccountFormState;
use ReturnTag\TagCore\Account\AccountRoute;
use ReturnTag\TagCore\Account\AccountTemplateRenderer;
use ReturnTag\TagCore\Account\AccountUrlProvider;
use ReturnTag\TagCore\Application\Account\ContinueOwnerConversation;
use ReturnTag\TagCore\Application\Account\OwnerConversationAccessState;
use ReturnTag\TagCore\Application\Account\OwnerConversationCollection;
use ReturnTag\TagCore\Application\Account\OwnerConversationContinuationStore;
use ReturnTag\TagCore\Application\Account\OwnerConversationSummary;
use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayProtector;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\Value\AccessTokenDigest;
use ReturnTag\TagCore\Domain\Conversation\ConversationStatus;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;
use WP_UnitTestCase;

/** Verifies privacy-minimized rendering and explicit POST continuation. */
final class AccountStageThreeTest extends WP_UnitTestCase {
	/** Empty conversations provide direction without exposing private relay data. */
	public function test_empty_conversation_page_points_back_to_owned_tags(): void {
		$html = ( new AccountTemplateRenderer( RETURNTAG_TAGCORE_DIR, new AccountUrlProvider() ) )->render_to_string(
			AccountRoute::CONVERSATIONS,
			new AccountFormResult( AccountFormState::READY ),
			null,
			null,
			null,
			new OwnerConversationCollection( OwnerConversationAccessState::READY, array() )
		);

		self::assertStringContainsString( 'No recovery conversations yet.', $html );
		self::assertStringContainsString( 'Return to My Tags', $html );
		self::assertStringNotContainsString( 'finder_email', $html );
		self::assertStringNotContainsString( 'owner_email', $html );
	}

	/** Summary cards expose only status, bounded activity, and the POST action. */
	public function test_conversation_page_renders_privacy_minimized_summary(): void {
		$now  = $this->now();
		$html = ( new AccountTemplateRenderer( RETURNTAG_TAGCORE_DIR, new AccountUrlProvider() ) )->render_to_string(
			AccountRoute::CONVERSATIONS,
			new AccountFormResult( AccountFormState::READY ),
			null,
			null,
			null,
			new OwnerConversationCollection(
				OwnerConversationAccessState::READY,
				array( new OwnerConversationSummary( 17, ConversationStatus::OPEN, $now, $now->modify( '-1 day' ), true ) )
			)
		);

		self::assertStringContainsString( 'Recovery conversations', $html );
		self::assertStringContainsString( 'Continue securely', $html );
		self::assertStringContainsString( 'method="post"', $html );
		self::assertStringContainsString( 'name="returntag_conversation_id" value="17"', $html );
		self::assertStringContainsString( 'name="returntag_account_conversation_nonce"', $html );
		self::assertStringNotContainsString( 'finder_email', $html );
		self::assertStringNotContainsString( 'owner_email', $html );
		self::assertStringNotContainsString( 'evidence', $html );
		self::assertStringNotContainsString( 'message_body', $html );
		self::assertStringNotContainsString( 'access_token', $html );
	}

	/** A valid same-site nonce returns a raw session only to the cookie boundary. */
	public function test_valid_post_continues_without_browser_authority_fields(): void {
		$session = str_repeat( 'A', 43 );
		$store   = $this->createMock( OwnerConversationContinuationStore::class );
		$store->expects( self::once() )->method( 'issue_owner_session' )->willReturn( true );

		$_SERVER['HTTP_ORIGIN'] = home_url( '/' );
		$_POST                  = array(
			AccountConversationFormHandler::NONCE_FIELD  => wp_create_nonce( AccountConversationFormHandler::NONCE_ACTION ),
			AccountConversationFormHandler::ACTION_FIELD => AccountConversationFormHandler::CONTINUE_ACTION,
			AccountConversationFormHandler::CONVERSATION_FIELD => '17',
		);

		$result = ( new AccountConversationFormHandler( $this->continuation( $store, $session ), new AccountFormRequestGuard() ) )->submit();

		self::assertSame( AccountConversationFeedback::NONE, $result->feedback );
		self::assertSame( $session, $result->session );
	}

	/** Injected authority and invalid nonce both fail before persistence. */
	public function test_invalid_post_fails_closed_without_existence_disclosure(): void {
		$store = $this->createMock( OwnerConversationContinuationStore::class );
		$store->expects( self::never() )->method( 'issue_owner_session' );
		$handler = new AccountConversationFormHandler( $this->continuation( $store, str_repeat( 'A', 43 ) ), new AccountFormRequestGuard() );

		$_SERVER['HTTP_ORIGIN'] = home_url( '/' );
		$_POST                  = array(
			AccountConversationFormHandler::NONCE_FIELD  => wp_create_nonce( AccountConversationFormHandler::NONCE_ACTION ),
			AccountConversationFormHandler::ACTION_FIELD => AccountConversationFormHandler::CONTINUE_ACTION,
			AccountConversationFormHandler::CONVERSATION_FIELD => '17',
			'owner_id'                                   => '42',
		);

		$result = $handler->submit();

		self::assertSame( AccountConversationFeedback::UNAVAILABLE, $result->feedback );
		self::assertNull( $result->session );
	}

	/** Restore browser request globals. */
	protected function tearDown(): void {
		$_POST = array();
		unset( $_SERVER['HTTP_ORIGIN'] );
		parent::tearDown();
	}

	/**
	 * Build an authenticated continuation with deterministic token material.
	 *
	 * @param OwnerConversationContinuationStore $store Persistence test double.
	 * @param string                             $raw_session Deterministic raw session.
	 */
	private function continuation( OwnerConversationContinuationStore $store, string $raw_session ): ContinueOwnerConversation {
		$session = $this->createMock( AuthenticatedSession::class );
		$session->method( 'current_user_id' )->willReturn( 42 );
		$flags     = new class() implements FeatureFlagReader {
			/**
			 * Enable only the Owner Account control.
			 *
			 * @param FeatureFlag $feature_flag Requested control.
			 */
			public function is_enabled( FeatureFlag $feature_flag ): bool {
				return FeatureFlag::OWNER_ACCOUNT === $feature_flag;
			}
		};
		$protector = $this->createMock( ConversationRelayProtector::class );
		$protector->method( 'generate_token' )->willReturn( $raw_session );
		$protector->method( 'token_digest' )->willReturn( AccessTokenDigest::from_digest( str_repeat( 'b', 64 ) ) );

		return new ContinueOwnerConversation( $session, $flags, $store, $protector, new FixedClock( $this->now() ) );
	}

	/** Return one fixed UTC instant. */
	private function now(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-08-11 12:00:00', new DateTimeZone( 'UTC' ) );
	}
}
