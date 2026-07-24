<?php
/**
 * Tests for the minimal WordPress plugin metadata.
 *
 * @package ReturnTag\TagCore\Tests\Unit\Bootstrap
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Bootstrap;

use PHPUnit\Framework\TestCase;

/**
 * Verifies the WordPress-recognizable foundation metadata and scope.
 */
final class PluginMetadataTest extends TestCase {
	/**
	 * Plugin bootstrap source under test.
	 *
	 * @var string
	 */
	private string $bootstrap;

	/**
	 * Load the plugin bootstrap source without executing WordPress code.
	 */
	protected function setUp(): void {
		parent::setUp();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local source fixture.
		$contents = file_get_contents( dirname( __DIR__, 3 ) . '/tagcore.php' );
		self::assertIsString( $contents );
		$this->bootstrap = $contents;
	}

	/**
	 * Ensure the plugin header declares the approved platform contract.
	 */
	public function test_declares_canonical_plugin_metadata(): void {
		self::assertStringContainsString( 'Plugin Name: TagCore', $this->bootstrap );
		self::assertStringContainsString( 'Version: 0.2.0', $this->bootstrap );
		self::assertStringContainsString( 'Requires at least: 6.9', $this->bootstrap );
		self::assertStringContainsString( 'Requires PHP: 8.3', $this->bootstrap );
		self::assertStringContainsString( 'Text Domain: tagcore', $this->bootstrap );
	}

	/**
	 * Ensure the foundation bootstrap does not register product behavior.
	 */
	public function test_does_not_register_product_behavior(): void {
		$forbidden_calls = array(
			'add_action(',
			'add_filter(',
			'register_rest_route(',
			'register_activation_hook(',
			'register_deactivation_hook(',
			'wp_mail(',
		);

		foreach ( $forbidden_calls as $forbidden_call ) {
			self::assertStringNotContainsString( $forbidden_call, $this->bootstrap );
		}
	}
}
