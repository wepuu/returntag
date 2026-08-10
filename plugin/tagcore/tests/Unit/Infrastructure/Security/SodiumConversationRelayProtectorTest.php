<?php
/**
 * Conversation relay cryptography tests.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Security;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;
use ReturnTag\TagCore\Infrastructure\Security\ConversationRelaySecrets;
use ReturnTag\TagCore\Infrastructure\Security\SodiumConversationRelayProtector;


/** Verifies token and AEAD domain separation. */
final class SodiumConversationRelayProtectorTest extends TestCase {
	/** Token shape, hashing, round-trip, and role binding are enforced. */
	public function test_tokens_are_url_safe_hash_only_and_messages_are_context_bound(): void {
		$subject = new SodiumConversationRelayProtector( ConversationRelaySecrets::from_keys( str_repeat( 'm', 32 ), str_repeat( 't', 32 ) ) );
		$token   = $subject->generate_token();
		self::assertMatchesRegularExpression( '/^[A-Za-z0-9_-]{43}$/D', $token );
		self::assertNotSame( $token, $subject->token_digest( $token )->value );
		$cipher = $subject->encrypt_message( 'A private recovery message.', 7, MessageSenderRole::OWNER );
		self::assertSame( 'A private recovery message.', $subject->decrypt_message( $cipher, 7, MessageSenderRole::OWNER ) );
		$this->expectException( \RuntimeException::class );
		$subject->decrypt_message( $cipher, 7, MessageSenderRole::FINDER );
	}
}
