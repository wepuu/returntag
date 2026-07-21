<?php
/**
 * WordPress integration coverage for the plugin bootstrap.
 *
 * @package ReturnTag\TagCore\Tests\Integration
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use WP_UnitTestCase;

/**
 * Verifies the plugin bootstrap inside a real WordPress test environment.
 */
final class PluginBootstrapTest extends WP_UnitTestCase {
	/**
	 * Ensure stable foundation constants are defined after WordPress loads.
	 */
	public function test_plugin_defines_stable_foundation_constants(): void {
		self::assertSame( '0.1.0', RETURNTAG_TAGCORE_VERSION );
		self::assertFileExists( RETURNTAG_TAGCORE_FILE );
		self::assertDirectoryExists( RETURNTAG_TAGCORE_DIR );
	}
}
