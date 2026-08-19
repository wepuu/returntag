<?php
/**
 * TagCore capability installation.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\WordPress;

use ReturnTag\TagCore\Admin\Capability;
use ReturnTag\TagCore\Admin\OperationalRoleProfileCatalog;
use WP_Role;

/**
 * Installs versioned administrator capabilities without altering user records.
 */
final class CapabilityInstaller {
	public const OPTION_NAME = 'returntag_capability_schema_version';

	private const TARGET_VERSION = 6;

	/**
	 * Create the installer.
	 *
	 * @param string $plugin_file Absolute plugin bootstrap path.
	 */
	public function __construct( private readonly string $plugin_file ) {
	}

	/**
	 * Register activation and authorized administrative reconciliation.
	 */
	public function register_hooks(): void {
		register_activation_hook( $this->plugin_file, array( $this, 'install' ) );
		add_action( 'admin_init', array( $this, 'maybe_install' ), 5 );
	}

	/**
	 * Reconcile capabilities from an authorized administrative request.
	 */
	public function maybe_install(): void {
		if ( ! current_user_can( 'activate_plugins' ) || $this->is_current() ) {
			return;
		}

		$this->install();
	}

	/**
	 * Add the current approved TagCore capabilities to administrators.
	 */
	public function install(): void {
		$role = get_role( 'administrator' );

		if ( ! $role instanceof WP_Role ) {
			return;
		}

		$catalog = new OperationalRoleProfileCatalog();
		foreach ( $catalog->owned_capabilities() as $capability ) {
			$role->add_cap( $capability );
		}

		foreach ( $catalog->profiles() as $slug => $profile ) {
			$operational_role = get_role( $slug );
			if ( ! $operational_role instanceof WP_Role ) {
				add_role( $slug, __( $profile['name'], 'tagcore' ), array_fill_keys( $profile['capabilities'], true ) ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Fixed internal catalog strings.
				$operational_role = get_role( $slug );
			}
			if ( ! $operational_role instanceof WP_Role ) {
				continue;
			}
			$wanted = array_fill_keys( $profile['capabilities'], true );
			foreach ( $catalog->owned_capabilities() as $capability ) {
				isset( $wanted[ $capability ] ) ? $operational_role->add_cap( $capability ) : $operational_role->remove_cap( $capability );
			}
			$operational_role->add_cap( 'read' );
		}

		$current_user = wp_get_current_user();

		if ( $current_user->exists() ) {
			$current_user->get_role_caps();
		}

		$current_version = (int) get_option( self::OPTION_NAME, 0 );

		if ( $current_version >= self::TARGET_VERSION ) {
			return;
		}

		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::TARGET_VERSION, '', false );
			return;
		}

		update_option( self::OPTION_NAME, self::TARGET_VERSION, false );
	}

	/**
	 * Determine whether the capability contract is current.
	 */
	private function is_current(): bool {
		return (int) get_option( self::OPTION_NAME, 0 ) >= self::TARGET_VERSION;
	}
}
