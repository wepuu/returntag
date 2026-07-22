<?php
/**
 * Migration runtime composition root.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/**
 * Wires the RT-101 migration runtime into WordPress.
 */
final class MigrationBootstrap {
	/**
	 * Register the migration lifecycle for the current WordPress site.
	 *
	 * @param string $plugin_file Absolute plugin bootstrap path.
	 */
	public static function register( string $plugin_file ): void {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			return;
		}

		$registry      = ( new MigrationRegistryFactory( $wpdb ) )->create();
		$version_store = new WordPressSchemaVersionStore();
		$lock          = new WordPressAdvisoryMigrationLock( $wpdb, get_current_blog_id() );
		$runner        = new MigrationRunner( $registry, $version_store, $lock );
		$schema_state  = new SchemaState( $version_store, $registry );
		$lifecycle     = new MigrationLifecycle( $plugin_file, $runner, $schema_state );

		$lifecycle->register_hooks();
	}
}
