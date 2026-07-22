<?php
/**
 * WordPress migration lifecycle adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use Throwable;

/**
 * Runs migrations only at approved administrative lifecycle boundaries.
 */
final class MigrationLifecycle {
	/**
	 * Whether the most recent non-activation migration attempt failed.
	 *
	 * @var bool
	 */
	private bool $migration_failed = false;

	/**
	 * Create the WordPress lifecycle adapter.
	 *
	 * @param string          $plugin_file Absolute plugin bootstrap path.
	 * @param MigrationRunner $runner      Migration executor.
	 * @param SchemaState     $schema_state Read-only schema readiness state.
	 */
	public function __construct(
		private readonly string $plugin_file,
		private readonly MigrationRunner $runner,
		private readonly SchemaState $schema_state
	) {
	}

	/**
	 * Register activation, upgrade, and administrator fallback hooks.
	 */
	public function register_hooks(): void {
		register_activation_hook( $this->plugin_file, array( $this, 'activate' ) );
		add_action( 'upgrader_process_complete', array( $this, 'after_plugin_upgrade' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'maybe_migrate_in_admin' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notice' ) );
	}

	/**
	 * Run all pending migrations during a supported single-site activation.
	 *
	 * @param bool $network_wide Whether WordPress requested network-wide activation.
	 */
	public function activate( bool $network_wide = false ): void {
		if ( $network_wide ) {
			wp_die(
				esc_html__( 'TagCore does not support network-wide activation. Activate it separately for each site.', 'tagcore' ),
				esc_html__( 'TagCore activation blocked', 'tagcore' ),
				array( 'response' => 400 )
			);
		}

		try {
			$this->runner->migrate();
		} catch ( Throwable ) {
			wp_die(
				esc_html__( 'TagCore could not prepare its database. No failed migration version was recorded; retry activation after checking the database.', 'tagcore' ),
				esc_html__( 'TagCore activation failed', 'tagcore' ),
				array( 'response' => 500 )
			);
		}
	}

	/**
	 * Run migrations after WordPress successfully updates this plugin.
	 *
	 * @param mixed                $upgrader   WordPress upgrader instance (unused).
	 * @param array<string, mixed> $hook_extra Update context supplied by WordPress.
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

		$this->run_with_admin_notice_on_failure();
	}

	/**
	 * Compensate for direct ZIP replacement only on an authorized admin request.
	 */
	public function maybe_migrate_in_admin(): void {
		if ( ! current_user_can( 'activate_plugins' ) || $this->schema_state->is_current() ) {
			return;
		}

		$this->run_with_admin_notice_on_failure();
	}

	/**
	 * Render a generic failure notice without exception or SQL detail.
	 */
	public function render_admin_notice(): void {
		if ( ! $this->migration_failed || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		esc_html_e( 'TagCore database preparation did not complete. No failed migration version was recorded; review database availability and retry.', 'tagcore' );
		echo '</p></div>';
	}

	/**
	 * Attempt migration while keeping raw failure detail out of admin output.
	 */
	private function run_with_admin_notice_on_failure(): void {
		try {
			$this->runner->migrate();
		} catch ( Throwable ) {
			$this->migration_failed = true;
		}
	}
}
