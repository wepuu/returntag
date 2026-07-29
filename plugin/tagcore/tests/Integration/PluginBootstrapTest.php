<?php
/**
 * WordPress integration coverage for the plugin bootstrap.
 *
 * @package ReturnTag\TagCore\Tests\Integration
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use WP_UnitTestCase;

/**
 * Verifies the plugin bootstrap inside a real WordPress test environment.
 */
final class PluginBootstrapTest extends WP_UnitTestCase {
	/**
	 * Ensure stable foundation constants are defined after WordPress loads.
	 */
	public function test_plugin_defines_stable_foundation_constants(): void {
		self::assertSame( '0.3.0', RETURNTAG_TAGCORE_VERSION );
		self::assertFileExists( RETURNTAG_TAGCORE_FILE );
		self::assertDirectoryExists( RETURNTAG_TAGCORE_DIR );
	}

	/**
	 * Ensure the bootstrap registers approved lifecycle and public-route hooks.
	 */
	public function test_plugin_registers_migration_lifecycle_without_running_schema_work_on_load(): void {
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );

		self::assertNotFalse( has_action( 'admin_init' ) );
		self::assertNotFalse( has_action( 'upgrader_process_complete' ) );
		self::assertNotFalse( has_action( 'activate_' . plugin_basename( RETURNTAG_TAGCORE_FILE ) ) );
		self::assertNotFalse( has_action( 'deactivate_' . plugin_basename( RETURNTAG_TAGCORE_FILE ) ) );
		self::assertNotFalse( has_action( 'template_redirect' ) );
		self::assertNotFalse( has_filter( 'template_include' ) );
		self::assertNotFalse( has_filter( 'query_vars' ) );
		self::assertFalse( get_option( WordPressSchemaVersionStore::OPTION_NAME, false ) );
	}
}
