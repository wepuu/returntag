<?php
/**
 * RT-315 Owner notification email integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use MockPHPMailer;
use PHPMailer\PHPMailer\PHPMailer;
use ReturnTag\TagCore\Application\FinderReport\FinderReportOwnerNotificationEmail;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Infrastructure\Email\WordPressFinderReportOwnerNotificationSender;
use WP_UnitTestCase;

/** Verifies the actual WordPress MIME message remains one-way and private. */
final class FinderReportOwnerEmailTest extends WP_UnitTestCase {
	/** Reset the shared WordPress test mailer before each assertion. */
	protected function setUp(): void {
		parent::setUp();
		if ( ! reset_phpmailer_instance() ) {
			$mailer             = new MockPHPMailer( true );
			$mailer::$validator = static fn( string $email ): bool => (bool) is_email( $email );
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The isolated WordPress test suite requires its non-sending mock mailer.
			$GLOBALS['phpmailer'] = $mailer;
		}
	}

	/** The email embeds only the approved JPEG and removes cross-party headers. */
	public function test_sender_builds_private_one_way_inline_email(): void {
		$valid_from                 = static fn(): string => 'wordpress@example.com';
		$inject_cross_party_headers = static function ( PHPMailer $mailer ): void {
			$mailer->addReplyTo( 'finder@example.net' );
			$mailer->addCC( 'finder-cc@example.net' );
			$mailer->addBCC( 'finder-bcc@example.net' );
		};
		add_filter( 'wp_mail_from', $valid_from );
		add_action( 'phpmailer_init', $inject_cross_party_headers, 5 );
		$jpeg  = "\xFF\xD8controlled-email-evidence\xFF\xD9";
		$email = new FinderReportOwnerNotificationEmail(
			new EmailAddress( 'owner@example.com' ),
			'I found it near <script>alert(1)</script> gate.',
			$jpeg,
			str_repeat( 'a', 64 )
		);

		try {
			$accepted = ( new WordPressFinderReportOwnerNotificationSender() )->send( $email );
		} finally {
			remove_action( 'phpmailer_init', $inject_cross_party_headers, 5 );
			remove_filter( 'wp_mail_from', $valid_from );
		}

		$mailer = tests_retrieve_phpmailer_instance();
		self::assertInstanceOf( MockPHPMailer::class, $mailer );
		$sent = $mailer->get_sent();
		self::assertNotFalse( $sent );
		self::assertTrue( $accepted );
		self::assertSame( 'A finder submitted a report about your ForgeTag', $sent->subject );
		self::assertSame( array(), $sent->cc );
		self::assertSame( array(), $sent->bcc );
		self::assertSame( array(), $mailer->getReplyToAddresses() );
		self::assertStringContainsString( 'Content-ID: &lt;returntag-finder-evidence@returntag.invalid&gt;', htmlspecialchars( $sent->body, ENT_QUOTES ) );
		self::assertStringContainsString( 'image/jpeg', $sent->body );
		self::assertStringContainsString( 'Content-Disposition: inline; filename=evidence.jpg', $sent->body );
		self::assertStringContainsString( 'I found it near &lt;script&gt;alert(1)&lt;/script&gt; gate.', quoted_printable_decode( $sent->body ) );
		self::assertStringContainsString( 'did not review its content', quoted_printable_decode( $sent->body ) );
		self::assertStringNotContainsString( 'passed ForgeTag safety checks', quoted_printable_decode( $sent->body ) );
		self::assertStringNotContainsString( 'A7R2W9', $sent->body );
		self::assertStringNotContainsString( 'finder@example.net', $sent->header . $sent->body );
		self::assertStringNotContainsString( 'owner@example.com', $sent->body );
		self::assertStringNotContainsString( str_repeat( 'a', 64 ), $sent->header . $sent->body );
		self::assertStringNotContainsString( 'Secure Reply', $sent->body );
	}
}
