<?php
/**
 * Public rewrite lifecycle adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

/**
 * Refreshes rewrite rules only at approved administrative lifecycle boundaries.
 */
final class PublicRewriteLifecycle {
	/**
	 * Create the lifecycle adapter.
	 *
	 * @param string                   $plugin_file Absolute TagCore bootstrap file.
	 * @param PublicTagRouteController $route Public route adapter.
	 */
	public function __construct(
		private readonly string $plugin_file,
		private readonly PublicTagRouteController $route
	) {
	}

	/**
	 * Register activation, deactivation, upgrade, and authorized compensation hooks.
	 */
	public function register_hooks(): void {
		register_activation_hook( $this->plugin_file, array( $this, 'activate' ) );
		register_deactivation_hook( $this->plugin_file, array( $this, 'deactivate' ) );
		add_action( 'upgrader_process_complete', array( $this, 'after_plugin_upgrade' ), 20, 2 );
		add_action( 'admin_init', array( $this, 'maybe_flush_in_admin' ), 20 );
	}

	/**
	 * Add the rule and refresh the site-scoped rewrite collection.
	 *
	 * @param bool $network_wide Whether WordPress requested network-wide activation.
	 */
	public function activate( bool $network_wide = false ): void {
		if ( $network_wide ) {
			return;
		}

		$this->route->register_rewrite_rule();
		flush_rewrite_rules( false );
	}

	/**
	 * Remove the rule and refresh the site-scoped rewrite collection.
	 *
	 * @param bool $network_wide Whether WordPress requested network-wide deactivation.
	 */
	public function deactivate( bool $network_wide = false ): void {
		if ( $network_wide ) {
			return;
		}

		$this->route->unregister_rewrite_rule();
		flush_rewrite_rules( false );
	}

	/**
	 * Refresh the route after a successful TagCore plugin update.
	 *
	 * @param mixed                $upgrader WordPress upgrader instance.
	 * @param array<string, mixed> $hook_extra Update context supplied by WordPress.
	 */
	public function after_plugin_upgrade( mixed $upgrader, array $hook_extra ): void {
		unset( $upgrader );

		if ( ! $this->is_tagcore_plugin_update( $hook_extra ) ) {
			return;
		}

		$this->route->register_rewrite_rule();
		flush_rewrite_rules( false );
	}

	/**
	 * Compensate for direct ZIP replacement on an authorized admin request.
	 */
	public function maybe_flush_in_admin(): void {
		if ( ! current_user_can( 'activate_plugins' ) || $this->stored_rules_include_route() ) {
			return;
		}

		$this->route->register_rewrite_rule();
		flush_rewrite_rules( false );
	}

	/**
	 * Determine whether the update context targets TagCore.
	 *
	 * @param array<string, mixed> $hook_extra Update context supplied by WordPress.
	 */
	private function is_tagcore_plugin_update( array $hook_extra ): bool {
		if ( 'update' !== ( $hook_extra['action'] ?? null ) || 'plugin' !== ( $hook_extra['type'] ?? null ) ) {
			return false;
		}

		$plugins = array();

		if ( isset( $hook_extra['plugin'] ) && is_string( $hook_extra['plugin'] ) ) {
			$plugins[] = $hook_extra['plugin'];
		}

		if ( isset( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			foreach ( $hook_extra['plugins'] as $plugin ) {
				if ( is_string( $plugin ) ) {
					$plugins[] = $plugin;
				}
			}
		}

		return in_array( plugin_basename( $this->plugin_file ), $plugins, true );
	}

	/**
	 * Determine whether persisted rewrite rules already include RT-301.
	 */
	private function stored_rules_include_route(): bool {
		$rules = get_option( 'rewrite_rules', array() );

		return is_array( $rules ) && array_key_exists( PublicTagRouteController::REWRITE_PATTERN, $rules );
	}
}
