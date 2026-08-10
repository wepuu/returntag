<?php
/**
 * Secure Reply email privacy integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use MockPHPMailer;
use PHPMailer\PHPMailer\PHPMailer;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;
use ReturnTag\TagCore\Infrastructure\Email\WordPressConversationRelayEmailSender;
use WP_UnitTestCase;

/** Verifies the actual WordPress relay email removes cross-party headers. */
final class ConversationRelayEmailTest extends WP_UnitTestCase {
	/** Reset the shared non-sending WordPress test mailer. */
	protected function setUp(): void {
		parent::setUp();
		if ( ! reset_phpmailer_instance() ) {
			$mailer             = new MockPHPMailer( true );
			$mailer::$validator = static fn( string $email ): bool => (bool) is_email( $email );
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The isolated WordPress test suite requires its non-sending mock mailer.
			$GLOBALS['phpmailer'] = $mailer;
		}
	}

	/** Injected Reply-To, CC, and BCC addresses are removed before submission. */
	public function test_sender_clears_injected_cross_party_headers(): void {
		$valid_from       = static fn(): string => 'wordpress@example.com';
		$inject_addresses = static function ( PHPMailer $mailer ): void {
			$mailer->addReplyTo( 'owner-reply@example.net' );
			$mailer->addCC( 'owner-cc@example.net' );
			$mailer->addBCC( 'owner-bcc@example.net' );
		};
		add_filter( 'wp_mail_from', $valid_from );
		add_action( 'phpmailer_init', $inject_addresses, 5 );
		try {
			$accepted = ( new WordPressConversationRelayEmailSender() )->send(
				new EmailAddress( 'finder@example.com' ),
				MessageSenderRole::FINDER,
				'Synthetic private reply.',
				'https://example.test/secure-reply/?token=opaque'
			);
		} finally {
			remove_action( 'phpmailer_init', $inject_addresses, 5 );
			remove_filter( 'wp_mail_from', $valid_from );
		}

		$mailer = tests_retrieve_phpmailer_instance();
		self::assertInstanceOf( MockPHPMailer::class, $mailer );
		$sent = $mailer->get_sent();
		self::assertNotFalse( $sent );
		self::assertTrue( $accepted );
		self::assertSame( array(), $sent->cc );
		self::assertSame( array(), $sent->bcc );
		self::assertSame( array(), $mailer->getReplyToAddresses() );
		self::assertSame( 'A private ForgeTag message is ready', $sent->subject );
		self::assertStringContainsString( 'Synthetic private reply.', $sent->body );
		self::assertStringContainsString( 'https://example.test/secure-reply/?token=opaque', $sent->body );
		self::assertStringNotContainsString( 'owner-reply@example.net', $sent->header . $sent->body );
		self::assertStringNotContainsString( 'owner-cc@example.net', $sent->header . $sent->body );
		self::assertStringNotContainsString( 'owner-bcc@example.net', $sent->header . $sent->body );
		self::assertStringNotContainsString( 'finder@example.com', $sent->body );
	}
}
