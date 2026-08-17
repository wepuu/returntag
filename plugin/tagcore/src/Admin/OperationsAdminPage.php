<?php
/**
 * Finder Reports and Users administration pages.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use ReturnTag\TagCore\Infrastructure\Migration\SchemaState;

/** Registers capability-separated operations surfaces backed by one bundle. */
final class OperationsAdminPage {
	public const FINDER_REPORTS_SLUG = 'tagcore-finder-reports';
	public const USERS_SLUG          = 'tagcore-users';
	private const SCRIPT_HANDLE      = 'returntag-tagcore-admin';

	/**
	 * Page-hook to surface mapping.
	 *
	 * @var array<string, string>
	 */
	private array $hooks = array();

	/**
	 * Create the page adapter.
	 *
	 * @param string      $plugin_dir Absolute plugin directory.
	 * @param SchemaState $schema_state Schema readiness.
	 */
	public function __construct( private readonly string $plugin_dir, private readonly SchemaState $schema_state ) {
	}

	/** Register WordPress menu and asset hooks. */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/** Register capability-separated submenu pages. */
	public function register_menu(): void {
		$this->add_page( self::FINDER_REPORTS_SLUG, __( 'TagCore Finder Reports', 'tagcore' ), __( 'Finder Reports', 'tagcore' ), Capability::MANAGE_DISPUTES, 'finder_reports' );
		$this->add_page( self::USERS_SLUG, __( 'TagCore Users', 'tagcore' ), __( 'Users', 'tagcore' ), Capability::VIEW_USERS, 'users' );
	}

	/**
	 * Register one operations submenu.
	 *
	 * @param string $slug Page slug.
	 * @param string $title Browser title.
	 * @param string $menu Menu label.
	 * @param string $capability Required capability.
	 * @param string $surface Frontend surface key.
	 */
	private function add_page( string $slug, string $title, string $menu, string $capability, string $surface ): void {
		$hook = add_submenu_page(
			BatchAdminPage::PAGE_SLUG,
			$title,
			$menu,
			$capability,
			$slug,
			function () use ( $capability, $surface ): void {
				$this->render( $capability, $surface );
			}
		);
		if ( is_string( $hook ) ) {
			$this->hooks[ $hook ] = $surface;
		}
	}

	/**
	 * Render one authorized React root.
	 *
	 * @param string $capability Required capability.
	 * @param string $surface Frontend surface key.
	 */
	public function render( string $capability, string $surface ): void {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You are not allowed to use this operations view.', 'tagcore' ), esc_html__( 'Access denied', 'tagcore' ), array( 'response' => 403 ) );
		}
		echo '<div class="wrap returntag-admin">';
		if ( ! $this->schema_state->is_current() ) {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'TagCore operations are unavailable until database preparation completes.', 'tagcore' );
			echo '</p></div></div>';
			return;
		}
		echo '<div id="returntag-admin-root" data-surface="' . esc_attr( $surface ) . '"></div></div>';
	}

	/**
	 * Enqueue the shared Admin bundle for registered pages.
	 *
	 * @param string $hook_suffix Current WordPress page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! isset( $this->hooks[ $hook_suffix ] ) || ! $this->schema_state->is_current() ) {
			return;
		}
		$surface     = $this->hooks[ $hook_suffix ];
		$asset_file  = $this->plugin_dir . '/build/admin/admin.tsx.asset.php';
		$script_file = $this->plugin_dir . '/build/admin/admin.tsx.js';
		$style_file  = $this->plugin_dir . '/build/admin/admin.tsx.css';
		if ( ! is_readable( $asset_file ) || ! is_readable( $script_file ) || ! is_readable( $style_file ) ) {
			return;
		}
		/**
		 * Compiled dependency metadata.
		 *
		 * @var array{dependencies: list<string>, version: string} $asset
		 */
		$asset = require $asset_file;
		wp_enqueue_script( self::SCRIPT_HANDLE, plugins_url( 'build/admin/admin.tsx.js', $this->plugin_dir . '/tagcore.php' ), $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( self::SCRIPT_HANDLE, plugins_url( 'build/admin/admin.tsx.css', $this->plugin_dir . '/tagcore.php' ), array( 'wp-components' ), $asset['version'] );
		wp_set_script_translations( self::SCRIPT_HANDLE, 'tagcore', $this->plugin_dir . '/languages' );
		$this->localize( $surface );
	}

	/**
	 * Localize capability-safe page configuration.
	 *
	 * @param string $surface Frontend surface key.
	 */
	private function localize( string $surface ): void {
		$user = wp_get_current_user();
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'returntagTagCoreAdmin',
			array(
				'nonce'             => wp_create_nonce( 'wp_rest' ),
				'restPath'          => '/tagcore/v1',
				'currentUser'       => $user->display_name,
				'currentTime'       => gmdate( DATE_ATOM ),
				'listUrl'           => admin_url( 'admin.php?page=' . BatchAdminPage::PAGE_SLUG ),
				'createUrl'         => admin_url( 'admin.php?page=' . BatchAdminPage::PAGE_SLUG . '&view=create' ),
				'tagsUrl'           => admin_url( 'admin.php?page=' . TagAdminPage::PAGE_SLUG ),
				'finderReportsUrl'  => admin_url( 'admin.php?page=' . self::FINDER_REPORTS_SLUG ),
				'usersUrl'          => admin_url( 'admin.php?page=' . self::USERS_SLUG ),
				'surface'           => $surface,
				'canManageTags'     => current_user_can( Capability::MANAGE_TAGS ),
				'canManageDisputes' => current_user_can( Capability::MANAGE_DISPUTES ),
				'canViewUsers'      => current_user_can( Capability::VIEW_USERS ),
				'canViewAudit'      => current_user_can( Capability::VIEW_AUDIT_LOGS ),
				'canEditUsers'      => current_user_can( 'edit_users' ),
			)
		);
	}
}
