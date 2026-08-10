<?php
/**
 * Plugin Name: TagCore
 * Description: Core plugin foundation for the ReturnTag platform.
 * Version: 0.4.0
 * Requires at least: 6.9
 * Requires PHP: 8.3
 * Author: ReturnTag
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tagcore
 * Domain Path: /languages
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RETURNTAG_TAGCORE_VERSION', '0.4.0' );
define( 'RETURNTAG_TAGCORE_FILE', __FILE__ );
define( 'RETURNTAG_TAGCORE_DIR', __DIR__ );

$returntag_autoload = RETURNTAG_TAGCORE_DIR . '/vendor/autoload.php';

if ( is_readable( $returntag_autoload ) ) {
	require_once $returntag_autoload;
}

$returntag_action_scheduler = RETURNTAG_TAGCORE_DIR . '/vendor/woocommerce/action-scheduler/action-scheduler.php';

if ( is_readable( $returntag_action_scheduler ) ) {
	require_once $returntag_action_scheduler;
}

if ( class_exists( ReturnTag\TagCore\Infrastructure\Migration\MigrationBootstrap::class ) ) {
	ReturnTag\TagCore\Infrastructure\Migration\MigrationBootstrap::register( RETURNTAG_TAGCORE_FILE );
}

if ( class_exists( ReturnTag\TagCore\Infrastructure\Queue\BatchGenerationBootstrap::class ) ) {
	ReturnTag\TagCore\Infrastructure\Queue\BatchGenerationBootstrap::register();
}

if ( class_exists( ReturnTag\TagCore\Infrastructure\Queue\ActivationOtpBootstrap::class ) ) {
	ReturnTag\TagCore\Infrastructure\Queue\ActivationOtpBootstrap::register();
}

if ( class_exists( ReturnTag\TagCore\Infrastructure\Queue\FinderReportBootstrap::class ) ) {
	ReturnTag\TagCore\Infrastructure\Queue\FinderReportBootstrap::register();
}

if ( class_exists( ReturnTag\TagCore\Infrastructure\Queue\ConversationRelayBootstrap::class ) ) {
	ReturnTag\TagCore\Infrastructure\Queue\ConversationRelayBootstrap::register();
}

if ( class_exists( ReturnTag\TagCore\PublicSite\PublicSiteBootstrap::class ) ) {
	ReturnTag\TagCore\PublicSite\PublicSiteBootstrap::register( RETURNTAG_TAGCORE_FILE );
}

if ( class_exists( ReturnTag\TagCore\Admin\AdminBootstrap::class ) ) {
	ReturnTag\TagCore\Admin\AdminBootstrap::register( RETURNTAG_TAGCORE_FILE );
}
