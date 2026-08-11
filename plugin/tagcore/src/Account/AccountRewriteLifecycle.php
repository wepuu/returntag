<?php
/**
 * Owner Account rewrite lifecycle.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

/**
 * Flushes Account rewrite rules only at approved lifecycle boundaries.
 */
final readonly class AccountRewriteLifecycle {
	/**
	 * Create the lifecycle adapter.
	 *
	 * @param string                 $plugin_file Absolute TagCore bootstrap file.
	 * @param AccountRouteController $route Account route adapter.
	 */
	public function __construct(
		private string $plugin_file,
		private AccountRouteController $route
	) {
	}

	/** Register activation, deactivation, and authorized compensation hooks. */
	public function register_hooks(): void {
		register_activation_hook( $this->plugin_file, array( $this, 'activate' ) );
		register_deactivation_hook( $this->plugin_file, array( $this, 'deactivate' ) );
		add_action( 'upgrader_process_complete', array( $this, 'after_plugin_upgrade' ), 25, 2 );
		add_action( 'admin_init', array( $this, 'maybe_flush_in_admin' ), 25 );
	}

	/**
	 * Register and flush Account rules on single-site activation.
	 *
	 * @param bool $network_wide Whether WordPress requested network activation.
	 */
	public function activate( bool $network_wide = false ): void {
		if ( $network_wide ) {
			return;
		}

		$this->route->register_rewrite_rules();
		flush_rewrite_rules( false );
	}

	/**
	 * Remove and flush Account rules on single-site deactivation.
	 *
	 * @param bool $network_wide Whether WordPress requested network deactivation.
	 */
	public function deactivate( bool $network_wide = false ): void {
		if ( $network_wide ) {
			return;
		}

		$this->route->unregister_rewrite_rules();
		flush_rewrite_rules( false );
	}

	/**
	 * Refresh Account rules after a successful TagCore update.
	 *
	 * @param mixed                $upgrader WordPress upgrader instance.
	 * @param array<string, mixed> $hook_extra Update context.
	 */
	public function after_plugin_upgrade( mixed $upgrader, array $hook_extra ): void {
		unset( $upgrader );

		if ( 'update' !== ( $hook_extra['action'] ?? null ) || 'plugin' !== ( $hook_extra['type'] ?? null ) ) {
			return;
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

		if ( ! in_array( plugin_basename( $this->plugin_file ), $plugins, true ) ) {
			return;
		}

		$this->route->register_rewrite_rules();
		flush_rewrite_rules( false );
	}

	/** Compensate for direct replacement on an authorized admin request. */
	public function maybe_flush_in_admin(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$rules = get_option( 'rewrite_rules', array() );

		if (
			is_array( $rules )
			&& array_key_exists( AccountRouteController::SIGN_IN_PATTERN, $rules )
			&& array_key_exists( AccountRouteController::OVERVIEW_PATTERN, $rules )
			&& array_key_exists( AccountRouteController::TAG_PATTERN, $rules )
		) {
			return;
		}

		$this->route->register_rewrite_rules();
		flush_rewrite_rules( false );
	}
}
