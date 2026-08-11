<?php
/**
 * Owner lifecycle WordPress mail boundary tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Infrastructure\Email\WordPressOwnerTestEmailSender;
use ReturnTag\TagCore\Infrastructure\Email\WordPressOwnerTransferEmailSender;
use WP_UnitTestCase;

/** Verifies WP Mail SMTP compatibility without a live SMTP configuration. */
final class OwnerLifecycleEmailTest extends WP_UnitTestCase {
	/** Test Email uses wp_mail with a plaintext, privacy-safe payload. */
	public function test_test_email_is_interceptable_at_wordpress_mail_boundary(): void {
		$captured = null;
		$filter   = static function ( ?bool $preempt, array $attributes ) use ( &$captured ): bool {
			unset( $preempt );
			$captured = $attributes;
			return true;
		};
		add_filter( 'pre_wp_mail', $filter, 10, 2 );
		try {
			$accepted = ( new WordPressOwnerTestEmailSender() )->send( new EmailAddress( 'owner@example.test' ) );
		} finally {
			remove_filter( 'pre_wp_mail', $filter, 10 );
		}

		self::assertTrue( $accepted );
		self::assertIsArray( $captured );
		self::assertSame( 'owner@example.test', $captured['to'] );
		self::assertSame( array( 'Content-Type: text/plain; charset=UTF-8' ), $captured['headers'] );
		self::assertStringNotContainsString( 'Reply-To', implode( "\n", $captured['headers'] ) );
	}

	/** Transfer invitation contains only target email and opaque acceptance URL. */
	public function test_transfer_email_does_not_expose_current_owner_address_or_reply_headers(): void {
		$captured = null;
		$filter   = static function ( ?bool $preempt, array $attributes ) use ( &$captured ): bool {
			unset( $preempt );
			$captured = $attributes;
			return true;
		};
		add_filter( 'pre_wp_mail', $filter, 10, 2 );
		try {
			$accepted = ( new WordPressOwnerTransferEmailSender() )->send( new EmailAddress( 'recipient@example.test' ), 'https://example.test/account/transfer/?transfer_token=opaque' );
		} finally {
			remove_filter( 'pre_wp_mail', $filter, 10 );
		}

		self::assertTrue( $accepted );
		self::assertIsArray( $captured );
		self::assertSame( 'recipient@example.test', $captured['to'] );
		self::assertStringContainsString( 'transfer_token=opaque', $captured['message'] );
		self::assertStringNotContainsString( 'current-owner@example.test', $captured['message'] );
		self::assertStringNotContainsString( 'Reply-To', implode( "\n", $captured['headers'] ) );
	}
}
