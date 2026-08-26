<?php
/**
 * Secure Reply email privacy integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Application\Email\TransactionalEmail;
use ReturnTag\TagCore\Application\Email\TransactionalEmailGateway;
use ReturnTag\TagCore\Application\Email\TransactionalEmailResult;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;
use ReturnTag\TagCore\Infrastructure\Email\WordPressConversationRelayEmailSender;
use WP_UnitTestCase;

/** Verifies relay messages remain one-recipient and privacy minimized. */
final class ConversationRelayEmailTest extends WP_UnitTestCase {
	/** Relay content delegates without cross-party headers or addresses. */
	public function test_sender_builds_private_gateway_request(): void {
		$gateway  = new class() implements TransactionalEmailGateway {
			/**
			 * Captured private request.
			 *
			 * @var TransactionalEmail
			 */
			public TransactionalEmail $email;

			/**
			 * Capture one request.
			 *
			 * @param TransactionalEmail $email Private in-memory request.
			 */
			public function send( TransactionalEmail $email ): TransactionalEmailResult {
				$this->email = $email;
				return TransactionalEmailResult::accepted( 'email_synthetic' );
			}
		};
		$accepted = ( new WordPressConversationRelayEmailSender( $gateway ) )->send(
			new EmailAddress( 'finder@example.test' ),
			MessageSenderRole::FINDER,
			'Synthetic private reply.',
			'https://example.test/secure-reply/?token=opaque',
			str_repeat( 'c', 64 )
		);

		self::assertTrue( $accepted );
		self::assertSame( 'finder@example.test', $gateway->email->recipient->value );
		self::assertStringContainsString( 'Synthetic private reply.', $gateway->email->text );
		self::assertStringContainsString( 'token=opaque', $gateway->email->text );
		self::assertStringNotContainsString( 'owner@example.test', $gateway->email->text );
	}
}
