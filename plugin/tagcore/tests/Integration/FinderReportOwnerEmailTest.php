<?php
/**
 * RT-315 Owner notification email integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Application\Email\TransactionalEmail;
use ReturnTag\TagCore\Application\Email\TransactionalEmailGateway;
use ReturnTag\TagCore\Application\Email\TransactionalEmailResult;
use ReturnTag\TagCore\Application\FinderReport\FinderReportOwnerNotificationEmail;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Infrastructure\Email\WordPressFinderReportOwnerNotificationSender;
use WP_UnitTestCase;

/** Verifies the gateway request remains one-way, private, and CID based. */
final class FinderReportOwnerEmailTest extends WP_UnitTestCase {
	/** The email includes exactly the approved in-memory JPEG. */
	public function test_sender_builds_private_one_way_inline_email(): void {
		$gateway = new class() implements TransactionalEmailGateway {
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
		$jpeg    = "\xFF\xD8controlled-email-evidence\xFF\xD9";
		$email   = new FinderReportOwnerNotificationEmail( new EmailAddress( 'owner@example.test' ), 'I found it near <script>alert(1)</script> gate.', $jpeg, str_repeat( 'a', 64 ) );

		$accepted = ( new WordPressFinderReportOwnerNotificationSender( $gateway ) )->send( $email );

		self::assertTrue( $accepted );
		self::assertSame( 'owner@example.test', $gateway->email->recipient->value );
		self::assertCount( 1, $gateway->email->attachments );
		self::assertSame( $jpeg, $gateway->email->attachments[0]->content );
		self::assertSame( 'returntag-finder-evidence@returntag.invalid', $gateway->email->attachments[0]->content_id );
		self::assertStringContainsString( 'I found it near &lt;script&gt;alert(1)&lt;/script&gt; gate.', $gateway->email->html );
		self::assertStringContainsString( 'did not review its content', $gateway->email->text );
		self::assertStringNotContainsString( 'finder@example.test', $gateway->email->text );
		self::assertStringNotContainsString( str_repeat( 'a', 64 ), $gateway->email->text );
	}
}
