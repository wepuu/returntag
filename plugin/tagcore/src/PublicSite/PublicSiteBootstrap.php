<?php
/**
 * TagCore public-site composition root.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use ReturnTag\TagCore\Application\PublicTag\PublicTagPagePolicy;
use ReturnTag\TagCore\Application\PublicTag\ResolvePublicTagPage;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailabilityPolicy;
use ReturnTag\TagCore\Application\Tag\TagIdInputNormalizer;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\SchemaState;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbPublicTagStateReader;
use ReturnTag\TagCore\Infrastructure\WordPressOptionFeatureFlagReader;
use wpdb;

/**
 * Wires the public Tag route into WordPress.
 */
final class PublicSiteBootstrap {
	/**
	 * Register the public route and its lifecycle hooks.
	 *
	 * @param string $plugin_file Absolute TagCore bootstrap file.
	 */
	public static function register( string $plugin_file ): void {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			return;
		}

		$plugin_dir    = dirname( $plugin_file );
		$gateway       = new WpdbGateway( $wpdb );
		$tables        = new TableNames( $wpdb->prefix );
		$feature_flags = new WordPressOptionFeatureFlagReader();
		$schema_state  = new SchemaState(
			new WordPressSchemaVersionStore(),
			( new MigrationRegistryFactory( $wpdb ) )->create()
		);
		$pages         = new ResolvePublicTagPage(
			new WpdbPublicTagStateReader( $gateway, $tables, new DatabaseDateTimeCodec() ),
			$feature_flags,
			new PublicTagPagePolicy( new TagActivationAvailabilityPolicy() )
		);
		$route         = new PublicTagRouteController(
			$plugin_dir,
			new PublicTagResponsePolicy(),
			new TagIdInputNormalizer(),
			$pages,
			$schema_state,
			new PublicTagTemplateRenderer( $plugin_dir )
		);

		$route->register_hooks();
		( new PublicRewriteLifecycle( $plugin_file, $route ) )->register_hooks();
	}
}
