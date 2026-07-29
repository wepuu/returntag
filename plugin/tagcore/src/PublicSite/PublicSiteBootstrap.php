<?php
/**
 * TagCore public-site composition root.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

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
		$route = new PublicTagRouteController(
			dirname( $plugin_file ),
			new PublicTagResponsePolicy()
		);

		$route->register_hooks();
		( new PublicRewriteLifecycle( $plugin_file, $route ) )->register_hooks();
	}
}
