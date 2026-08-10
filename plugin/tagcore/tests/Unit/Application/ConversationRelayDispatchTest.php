<?php
/**
 * Secure Reply dispatch workflow tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\Conversation\ConversationDispatch;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayEmailSender;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayLinkBuilder;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayOwnerResolver;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayProtector;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayStore;
use ReturnTag\TagCore\Application\Conversation\DispatchConversationMessage;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\FinderReport\FinderEmailProtector;
use ReturnTag\TagCore\Application\Persistence\Record\AccessTokenRecord;
use ReturnTag\TagCore\Application\Persistence\Record\ConversationRecord;
use ReturnTag\TagCore\Application\Persistence\Record\MessageRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewAccessTokenRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewConversationRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewMessageRecord;
use ReturnTag\TagCore\Application\Persistence\Value\AccessTokenDigest;
use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\MessageCiphertext;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Conversation\ConversationStatus;
use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;

/** Verifies role-bound delivery and terminal provider failure convergence. */
final class ConversationRelayDispatchTest extends TestCase {
	/** Owner plaintext is delivered only to the verified Finder with a Finder link. */
	public function test_owner_message_targets_verified_finder_and_marks_provider_acceptance(): void {
		$now        = $this->now();
		$dispatch   = $this->dispatch( MessageSenderRole::OWNER, $now );
		$finder     = new EmailAddress( 'finder@example.test' );
		$raw_token  = str_repeat( 'T', 43 );
		$token_hash = AccessTokenDigest::from_digest( str_repeat( 'a', 64 ) );
		$link       = new AccessTokenRecord(
			81,
			new NewAccessTokenRecord( 17, 'finder_continue_conversation', MessageSenderRole::FINDER, $token_hash, $now->modify( '+24 hours' ), null, null, $now )
		);
		$store      = $this->createMock( ConversationRelayStore::class );
		$store->method( 'claim_dispatch' )->with( 31, $now )->willReturn( $dispatch );
		$store->expects( self::once() )->method( 'issue_link' )->with(
			17,
			'finder_continue_conversation',
			MessageSenderRole::FINDER,
			$token_hash,
			self::callback( static fn( DateTimeImmutable $expiry ): bool => $now->modify( '+24 hours' )->getTimestamp() === $expiry->getTimestamp() ),
			$now
		)->willReturn( $link );
		$store->expects( self::once() )->method( 'dispatch_is_active' )->with( 31, 81, $now )->willReturn( true );
		$store->expects( self::once() )->method( 'mark_sent' )->with( 31, $now )->willReturn( true );
		$store->expects( self::never() )->method( 'mark_failed' );
		$protector = $this->createMock( ConversationRelayProtector::class );
		$protector->method( 'generate_token' )->willReturn( $raw_token );
		$protector->method( 'token_digest' )->with( $raw_token )->willReturn( $token_hash );
		$protector->method( 'decrypt_message' )->with( $dispatch->message->data->body_ciphertext, 17, MessageSenderRole::OWNER )->willReturn( 'Synthetic private reply.' );
		$finder_emails = $this->createMock( FinderEmailProtector::class );
		$finder_emails->method( 'decrypt_email' )->with( $dispatch->conversation->data->finder_email_ciphertext, 29 )->willReturn( $finder );
		$owners = $this->createMock( ConversationRelayOwnerResolver::class );
		$owners->expects( self::never() )->method( 'resolve' );
		$links = $this->createMock( ConversationRelayLinkBuilder::class );
		$links->method( 'build' )->with( $raw_token )->willReturn( 'https://example.test/secure-reply/?token=opaque' );
		$sender = $this->createMock( ConversationRelayEmailSender::class );
		$sender->expects( self::once() )->method( 'send' )->with( $finder, MessageSenderRole::FINDER, 'Synthetic private reply.', 'https://example.test/secure-reply/?token=opaque' )->willReturn( true );

		$service = new DispatchConversationMessage( $this->enabled_flags(), $store, $protector, $finder_emails, $owners, $sender, $links, $this->clock( $now ) );

		self::assertTrue( $service->execute( 31 ) );
	}

	/** Provider rejection revokes the unsent continuation link and terminates work. */
	public function test_provider_rejection_revokes_link_and_marks_failed(): void {
		$now        = $this->now();
		$dispatch   = $this->dispatch( MessageSenderRole::SYSTEM, $now );
		$owner      = new EmailAddress( 'owner@example.test' );
		$raw_token  = str_repeat( 'T', 43 );
		$token_hash = AccessTokenDigest::from_digest( str_repeat( 'b', 64 ) );
		$link       = new AccessTokenRecord( 82, new NewAccessTokenRecord( 17, 'owner_secure_reply', MessageSenderRole::OWNER, $token_hash, $now->modify( '+24 hours' ), null, null, $now ) );
		$store      = $this->createMock( ConversationRelayStore::class );
		$store->method( 'claim_dispatch' )->willReturn( $dispatch );
		$store->method( 'issue_link' )->willReturn( $link );
		$store->method( 'dispatch_is_active' )->with( 32, 82, $now )->willReturn( true );
		$store->expects( self::once() )->method( 'revoke_token' )->with( 82, $now );
		$store->expects( self::once() )->method( 'mark_failed' )->with( 32, $now )->willReturn( true );
		$store->expects( self::never() )->method( 'mark_sent' );
		$protector = $this->createMock( ConversationRelayProtector::class );
		$protector->method( 'generate_token' )->willReturn( $raw_token );
		$protector->method( 'token_digest' )->willReturn( $token_hash );
		$owners = $this->createMock( ConversationRelayOwnerResolver::class );
		$owners->method( 'resolve' )->with( 42 )->willReturn( $owner );
		$sender = $this->createMock( ConversationRelayEmailSender::class );
		$sender->expects( self::once() )->method( 'send' )->with( $owner, MessageSenderRole::OWNER, null, 'https://example.test/secure-reply/?token=opaque' )->willReturn( false );
		$links = $this->createMock( ConversationRelayLinkBuilder::class );
		$links->method( 'build' )->willReturn( 'https://example.test/secure-reply/?token=opaque' );

		$service = new DispatchConversationMessage( $this->enabled_flags(), $store, $protector, $this->createMock( FinderEmailProtector::class ), $owners, $sender, $links, $this->clock( $now ) );

		self::assertFalse( $service->execute( 32 ) );
	}

	/** A terminal Conversation detected by the final check never reaches the provider. */
	public function test_terminal_conversation_cancels_before_provider_call(): void {
		$now        = $this->now();
		$dispatch   = $this->dispatch( MessageSenderRole::SYSTEM, $now );
		$owner      = new EmailAddress( 'owner@example.test' );
		$raw_token  = str_repeat( 'T', 43 );
		$token_hash = AccessTokenDigest::from_digest( str_repeat( 'd', 64 ) );
		$link       = new AccessTokenRecord( 83, new NewAccessTokenRecord( 17, 'owner_secure_reply', MessageSenderRole::OWNER, $token_hash, $now->modify( '+24 hours' ), null, null, $now ) );
		$store      = $this->createMock( ConversationRelayStore::class );
		$store->method( 'claim_dispatch' )->willReturn( $dispatch );
		$store->method( 'issue_link' )->willReturn( $link );
		$store->expects( self::once() )->method( 'dispatch_is_active' )->with( 32, 83, $now )->willReturn( false );
		$store->expects( self::once() )->method( 'revoke_token' )->with( 83, $now );
		$store->expects( self::once() )->method( 'mark_failed' )->with( 32, $now )->willReturn( false );
		$store->expects( self::never() )->method( 'mark_sent' );
		$protector = $this->createMock( ConversationRelayProtector::class );
		$protector->method( 'generate_token' )->willReturn( $raw_token );
		$protector->method( 'token_digest' )->willReturn( $token_hash );
		$owners = $this->createMock( ConversationRelayOwnerResolver::class );
		$owners->method( 'resolve' )->with( 42 )->willReturn( $owner );
		$sender = $this->createMock( ConversationRelayEmailSender::class );
		$sender->expects( self::never() )->method( 'send' );

		$service = new DispatchConversationMessage( $this->enabled_flags(), $store, $protector, $this->createMock( FinderEmailProtector::class ), $owners, $sender, $this->createMock( ConversationRelayLinkBuilder::class ), $this->clock( $now ) );

		self::assertFalse( $service->execute( 32 ) );
	}

	/**
	 * Build one synthetic claimed dispatch projection.
	 *
	 * @param MessageSenderRole $role Sender role.
	 * @param DateTimeImmutable $now Fixed instant.
	 */
	private function dispatch( MessageSenderRole $role, DateTimeImmutable $now ): ConversationDispatch {
		$ciphertext   = MessageCiphertext::from_encrypted_bytes( 'opaque-message-envelope' );
		$message_id   = MessageSenderRole::SYSTEM === $role ? 32 : 31;
		$message      = new MessageRecord( $message_id, new NewMessageRecord( 17, $role, $ciphertext, DeliveryStatus::QUEUED, null, null, $now ) );
		$conversation = new ConversationRecord(
			17,
			new NewConversationRecord( 'A7R2W9', 42, EmailCiphertext::from_encrypted_bytes( 'opaque-finder-email' ), LookupDigest::from_digest( str_repeat( 'c', 64 ) ), $now, ConversationStatus::OPEN, $now->modify( '+30 days' ), $now, $now )
		);
		return new ConversationDispatch( $message, $conversation, 29, 42 );
	}

	/** Return both relay kill switches enabled. */
	private function enabled_flags(): FeatureFlagReader {
		$flags = $this->createMock( FeatureFlagReader::class );
		$flags->method( 'is_enabled' )->willReturn( true );
		return $flags;
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

	/** Return the fixed UTC test instant. */
	private function now(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-08-10 12:00:00', new DateTimeZone( 'UTC' ) );
	}
}
