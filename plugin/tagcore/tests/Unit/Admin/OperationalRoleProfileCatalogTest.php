<?php
/**
 * RT-329 fixed role profile coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Admin\Capability;
use ReturnTag\TagCore\Admin\OperationalRoleProfileCatalog;

/** Verifies exact least-privilege role sets. */
final class OperationalRoleProfileCatalogTest extends TestCase {
	/** Every fixed profile has the shared floor and no role-editor grant. */
	public function test_installs_eight_fixed_profiles_without_role_configuration_grant(): void {
		$profiles = ( new OperationalRoleProfileCatalog() )->profiles();
		self::assertCount( 8, $profiles );
		foreach ( $profiles as $profile ) {
			self::assertContains( 'read', $profile['capabilities'] );
			self::assertContains( Capability::MANAGE_RETURNTAG, $profile['capabilities'] );
			self::assertNotContains( Capability::MANAGE_ROLE_PROFILES, $profile['capabilities'] );
			self::assertNotContains( 'edit_users', $profile['capabilities'] );
		}
		self::assertSame(
			array(
				'returntag_batch_operator'         => array( 'read', Capability::MANAGE_RETURNTAG, Capability::MANAGE_BATCHES ),
				'returntag_tag_operator'           => array( 'read', Capability::MANAGE_RETURNTAG, Capability::MANAGE_TAGS ),
				'returntag_tag_lifecycle_operator' => array( 'read', Capability::MANAGE_RETURNTAG, Capability::MANAGE_TAGS, Capability::MANAGE_TAG_LIFECYCLE ),
				'returntag_dispute_operator'       => array( 'read', Capability::MANAGE_RETURNTAG, Capability::MANAGE_DISPUTES, Capability::MANAGE_FINDER_REPORT_DECISIONS ),
				'returntag_user_support'           => array( 'read', Capability::MANAGE_RETURNTAG, Capability::VIEW_USERS, Capability::MANAGE_TAGS ),
				'returntag_audit_viewer'           => array( 'read', Capability::MANAGE_RETURNTAG, Capability::VIEW_AUDIT_LOGS ),
				'returntag_retention_operator'     => array( 'read', Capability::MANAGE_RETURNTAG, Capability::MANAGE_RETENTION ),
				'returntag_operations_manager'     => array(
					'read',
					Capability::MANAGE_RETURNTAG,
					Capability::MANAGE_BATCHES,
					Capability::MANAGE_TAGS,
					Capability::MANAGE_TAG_LIFECYCLE,
					Capability::MANAGE_DISPUTES,
					Capability::MANAGE_FINDER_REPORT_DECISIONS,
					Capability::VIEW_USERS,
					Capability::VIEW_AUDIT_LOGS,
					Capability::MANAGE_RETENTION,
				),
			),
			array_map( static fn( array $profile ): array => $profile['capabilities'], $profiles )
		);
	}

	/** Operations Manager remains operational rather than site-administrative. */
	public function test_operations_manager_has_operational_caps_but_not_governance_or_site_admin(): void {
		$caps = ( new OperationalRoleProfileCatalog() )->profiles()['returntag_operations_manager']['capabilities'];
		self::assertContains( Capability::MANAGE_RETENTION, $caps );
		self::assertContains( Capability::VIEW_AUDIT_LOGS, $caps );
		self::assertNotContains( Capability::MANAGE_ROLE_PROFILES, $caps );
		self::assertNotContains( 'manage_options', $caps );
	}
}
