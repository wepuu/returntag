<?php
/**
 * Canonical ReturnTag operational feature flags.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application;

/**
 * Identifies the global operational controls approved by the PRD.
 */
enum FeatureFlag: string {
	case GLOBAL_ACTIVATION   = 'returntag_global_activation_enabled';
	case FINDER_CONTACT      = 'returntag_finder_contact_enabled';
	case EMAIL_DISPATCH      = 'returntag_email_dispatch_enabled';
	case WOOCOMMERCE_ACCOUNT = 'returntag_woocommerce_account_enabled';
}
