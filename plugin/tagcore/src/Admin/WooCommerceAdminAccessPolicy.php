<?php
/**
 * WooCommerce admin-access compatibility for TagCore operators.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

/**
 * Allows narrowly authorized TagCore operators through WooCommerce's blanket redirect.
 */
final class WooCommerceAdminAccessPolicy {
	/**
	 * Keep WooCommerce's redirect unless the user has a TagCore administration capability.
	 *
	 * Individual TagCore pages and REST routes still enforce their exact capabilities.
	 *
	 * @param bool $prevent_access Whether WooCommerce intends to prevent wp-admin access.
	 */
	public function filter_prevent_admin_access( bool $prevent_access ): bool {
		if ( ! $prevent_access ) {
			return false;
		}

		foreach ( self::tagcore_capabilities() as $capability ) {
			if ( current_user_can( $capability ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Capabilities that identify a TagCore back-office operator.
	 *
	 * @return list<string>
	 */
	private static function tagcore_capabilities(): array {
		return array(
			Capability::MANAGE_RETURNTAG,
			Capability::MANAGE_BATCHES,
			Capability::MANAGE_TAGS,
			Capability::MANAGE_TAG_LIFECYCLE,
			Capability::MANAGE_DISPUTES,
			Capability::MANAGE_FINDER_REPORT_DECISIONS,
			Capability::VIEW_USERS,
			Capability::VIEW_AUDIT_LOGS,
		);
	}
}
