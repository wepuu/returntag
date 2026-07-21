<?php
/**
 * Uninstall handler for TagCore.
 *
 * RT-001 deliberately performs no data deletion. Future uninstall behavior
 * must follow the approved data-retention and immutable Tag ID requirements.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
