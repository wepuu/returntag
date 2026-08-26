<?php
/**
 * Owner lifecycle provider-neutral email boundary tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Application\Email\TransactionalEmail;
use ReturnTag\TagCore\Application\Email\TransactionalEmailGateway;
use ReturnTag\TagCore\Application\Email\TransactionalEmailResult;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Infrastructure\Email\WordPressOwnerTestEmailSender;
use ReturnTag\TagCore\Infrastructure\Email\WordPressOwnerTransferEmailSender;
use WP_UnitTestCase;

/** Verifies lifecycle messages expose no cross-party address or reply route. */
final class OwnerLifecycleEmailTest extends WP_UnitTestCase {
	/** Test Email delegates one private plaintext request. */
	public function test_test_email_uses_provider_neutral_gateway(): void {
		$gateway  = $this->gateway();
		$accepted = ( new WordPressOwnerTestEmailSender( $gateway ) )->send( new EmailAddress( 'owner@example.test' ), str_repeat( 'a', 64 ) );

		self::assertTrue( $accepted );
		self::assertSame( 'owner@example.test', $gateway->email->recipient->value );
		self::assertSame( 'owner_test', $gateway->email->purpose );
		self::assertStringNotContainsString( 'Reply-To', $gateway->email->text );
	}

	/** Transfer contains only the target and opaque same-site acceptance URL. */
	public function test_transfer_email_preserves_private_boundary(): void {
		$gateway  = $this->gateway();
		$accepted = ( new WordPressOwnerTransferEmailSender( $gateway ) )->send( new EmailAddress( 'recipient@example.test' ), 'https://example.test/account/transfer/?transfer_token=opaque', str_repeat( 'b', 64 ) );

		self::assertTrue( $accepted );
		self::assertSame( 'recipient@example.test', $gateway->email->recipient->value );
		self::assertStringContainsString( 'transfer_token=opaque', $gateway->email->text );
		self::assertStringNotContainsString( 'current-owner@example.test', $gateway->email->text );
	}

	/** Return a capture-only provider-neutral gateway. */
	private function gateway(): TransactionalEmailGateway {
		return new class() implements TransactionalEmailGateway {
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
	}
}
