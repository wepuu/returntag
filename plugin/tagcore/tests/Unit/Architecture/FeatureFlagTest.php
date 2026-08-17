<?php
/**
 * Feature flag contract tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\FeatureFlag;

/**
 * Verifies the frozen global feature flag names.
 */
final class FeatureFlagTest extends TestCase {
	/**
	 * Ensure the contract contains exactly the approved option names.
	 */
	public function test_defines_only_the_approved_global_flags(): void {
		$option_names = array_map(
			static fn ( FeatureFlag $feature_flag ): string => $feature_flag->value,
			FeatureFlag::cases()
		);

		self::assertSame(
			array(
				'returntag_global_activation_enabled',
				'returntag_finder_contact_enabled',
				'returntag_finder_evidence_enabled',
				'returntag_email_dispatch_enabled',
				'returntag_woocommerce_account_enabled',
				'returntag_owner_account_enabled',
				'returntag_owner_lifecycle_enabled',
				'returntag_admin_sensitive_preview_enabled',
				'returntag_admin_tag_lifecycle_enabled',
				'returntag_admin_finder_report_decisions_enabled',
			),
			$option_names
		);
	}
}
