<?php
/**
 * Secure Reply public presentation contract tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\PublicSite;

use PHPUnit\Framework\TestCase;

/** Verifies the sensitive page remains semantic, local-only, and privacy-safe. */
final class SecureReplyTemplateContractTest extends TestCase {
	/** The thread form must retain its accessible bounded plain-text contract. */
	public function test_template_has_accessible_post_only_plain_text_form(): void {
		$template = $this->contents( 'templates/public/secure-reply.php' );

		self::assertStringContainsString( 'aria-labelledby="returntag-reply-title"', $template );
		self::assertStringContainsString( '<h1 id="returntag-reply-title">', $template );
		self::assertStringContainsString( '<form class="returntag-public__form" method="post"', $template );
		self::assertStringContainsString( 'name="_returntag_nonce"', $template );
		self::assertStringContainsString( '<label for="returntag-reply-message">', $template );
		self::assertStringContainsString( 'name="message" minlength="10" maxlength="500" aria-describedby="returntag-reply-message-hint" autocomplete="off" required', $template );
		self::assertStringContainsString( 'id="returntag-reply-message-hint"', $template );
		self::assertStringContainsString( 'aria-label="<?php esc_attr_e( \'Conversation messages\'', $template );
		self::assertStringContainsString( 'returntag-public__conversation-item--<?php echo esc_attr( $is_mine ? \'mine\' : \'peer\' ); ?>', $template );
		self::assertStringContainsString( 'nl2br( esc_html( $message->body ) )', $template );
		self::assertStringNotContainsString( 'type="file"', $template );
		self::assertStringNotContainsString( 'type="email"', $template );
	}

	/** The page must expose generic, assistive-technology-friendly recovery feedback. */
	public function test_template_has_generic_feedback_and_recovery_contract(): void {
		$template = $this->contents( 'templates/public/secure-reply.php' );

		self::assertStringContainsString( 'role="status"', $template );
		self::assertStringContainsString( 'role="alert"', $template );
		self::assertStringContainsString( "'Message saved. Delivery continues in the background.'", $template );
		self::assertStringContainsString( "'Message was not sent. Check the 10–500 character limit", $template );
		self::assertSame( 2, substr_count( $template, "'Return to ForgeTag home'" ) );
		self::assertStringNotContainsString( 'delivered', strtolower( $template ) );
	}

	/** Participant safety forms must be role-specific, explicit, and payload-free. */
	public function test_template_has_confirmed_role_specific_terminal_actions(): void {
		$template = $this->contents( 'templates/public/secure-reply.php' );

		self::assertStringContainsString( 'aria-labelledby="returntag-safety-title"', $template );
		self::assertStringContainsString( "'owner_report_block' : 'finder_close'", $template );
		self::assertStringContainsString( 'name="confirm_terminal_action" value="1" required', $template );
		self::assertStringContainsString( "__( 'Report and block', 'tagcore' )", $template );
		self::assertStringContainsString( "__( 'End conversation', 'tagcore' )", $template );
		foreach ( array( 'report_reason', 'reason_text', 'conversation_id', 'owner_id', 'finder_id' ) as $forbidden ) {
			self::assertStringNotContainsString( $forbidden, $template );
		}
	}

	/** The standalone page must not load remote assets or expose private fields. */
	public function test_template_omits_remote_assets_and_private_identifiers(): void {
		$template = strtolower( $this->contents( 'templates/public/secure-reply.php' ) );

		foreach ( array( 'http://', 'https://', 'owner_email', 'finder_email', 'item_name', 'tag_id', 'source_filename', 'original_filename' ) as $forbidden ) {
			self::assertStringNotContainsString( $forbidden, $template );
		}
		self::assertStringContainsString( 'meta name="referrer" content="no-referrer"', $template );
		self::assertStringContainsString( 'meta name="robots" content="noindex,nofollow,noarchive"', $template );
	}

	/** Cookie and CSP source must preserve the frozen sensitive-page controls. */
	public function test_controller_keeps_secure_cookie_and_local_only_csp_contract(): void {
		$controller = $this->contents( 'src/PublicSite/SecureReplyRouteController.php' );

		self::assertSame( 2, substr_count( $controller, "'secure'   => true" ) );
		self::assertSame( 2, substr_count( $controller, "'httponly' => true" ) );
		self::assertSame( 2, substr_count( $controller, "'samesite' => 'Strict'" ) );
		self::assertStringContainsString( "default-src \'none\'; style-src \'self\'; form-action \'self\'", $controller );
		self::assertStringContainsString( "wp_safe_redirect( home_url( '/secure-reply/' ), 303", $controller );
		self::assertStringContainsString( "private const FEEDBACK_COOKIE = 'returntag_reply_feedback';", $controller );
		self::assertStringContainsString( 'in_array( $value, array( self::FEEDBACK_SENT, self::FEEDBACK_FAILED ), true )', $controller );
		self::assertStringContainsString( '$sent = $this->runtime->submit_message->execute(', $controller );
		self::assertStringContainsString( '$sent ? self::FEEDBACK_SENT : self::FEEDBACK_FAILED', $controller );
		self::assertStringContainsString( 'catch ( \\Throwable )', $controller );
	}

	/** Controller source must keep terminal mutations inside the guarded POST path. */
	public function test_controller_guards_terminal_actions_and_clears_access_cookies(): void {
		$controller = $this->contents( 'src/PublicSite/SecureReplyRouteController.php' );

		self::assertStringContainsString( '! $this->guard->is_same_site()', $controller );
		self::assertStringContainsString( "! \$this->guard->valid_nonce( '_returntag_nonce', 'returntag_secure_reply' )", $controller );
		self::assertStringContainsString( 'ConversationSafetyAction::tryFrom( $action )', $controller );
		self::assertStringContainsString( "'1' === \$this->guard->post_string( 'confirm_terminal_action', 1 )", $controller );
		self::assertStringContainsString( '$this->clear( self::LINK_COOKIE );', $controller );
		self::assertStringContainsString( '$this->clear( self::SESSION_COOKIE );', $controller );
		foreach ( array( "post_string( 'conversation_id'", "post_string( 'owner_id'", "post_string( 'finder_id'", "post_string( 'target_status'" ) as $forbidden ) {
			self::assertStringNotContainsString( $forbidden, $controller );
		}
	}

	/**
	 * Read one project file or fail the test safely.
	 *
	 * @param string $relative_path Plugin-relative path.
	 */
	private function contents( string $relative_path ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a trusted local project fixture, never a URL.
		$contents = file_get_contents( dirname( __DIR__, 3 ) . '/' . $relative_path );
		self::assertIsString( $contents );
		return $contents;
	}
}
