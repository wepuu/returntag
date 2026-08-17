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
	case GLOBAL_ACTIVATION             = 'returntag_global_activation_enabled';
	case FINDER_CONTACT                = 'returntag_finder_contact_enabled';
	case FINDER_EVIDENCE               = 'returntag_finder_evidence_enabled';
	case EMAIL_DISPATCH                = 'returntag_email_dispatch_enabled';
	case WOOCOMMERCE_ACCOUNT           = 'returntag_woocommerce_account_enabled';
	case OWNER_ACCOUNT                 = 'returntag_owner_account_enabled';
	case OWNER_LIFECYCLE               = 'returntag_owner_lifecycle_enabled';
	case ADMIN_SENSITIVE_PREVIEW       = 'returntag_admin_sensitive_preview_enabled';
	case ADMIN_TAG_LIFECYCLE           = 'returntag_admin_tag_lifecycle_enabled';
	case ADMIN_FINDER_REPORT_DECISIONS = 'returntag_admin_finder_report_decisions_enabled';
}
