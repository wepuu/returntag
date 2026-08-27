<?php
/**
 * RT-329 retention Event identity coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Admin;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Admin\AdminGovernanceEventIdentityPolicy;

/** Verifies the metadata-free retention Event identity allowlist. */
final class AdminGovernanceEventIdentityPolicyTest extends TestCase {
	/** Only fixed Event, actor, Task ID, and correlation combinations pass. */
	public function test_allows_only_fixed_retention_event_identities(): void {
		$policy = new AdminGovernanceEventIdentityPolicy();

		self::assertTrue( $policy->allows( 'retention_task_run_completed', 'system', null, 'retention_task', 'auth_challenge_cleanup', null ) );
		self::assertTrue( $policy->allows( 'retention_task_run_requested', 'user', 42, 'retention_task', 'activation_cleanup', null ) );
		self::assertTrue( $policy->allows( 'retention_task_run_completed', 'system', null, 'retention_task', 'finder_evidence_cleanup', null ) );
		self::assertFalse( $policy->allows( 'retention_task_run_requested', 'user', null, 'retention_task', 'activation_cleanup', null ) );
		self::assertFalse( $policy->allows( 'retention_task_run_completed', 'user', 42, 'retention_task', 'finder_evidence_cleanup', null ) );
		self::assertFalse( $policy->allows( 'retention_task_run_completed', 'system', null, 'retention_task', 'custom-task', null ) );
		self::assertFalse( $policy->allows( 'retention_task_run_completed', 'system', null, 'retention_task', 'finder-evidence', null ) );
		self::assertFalse( $policy->allows( 'retention_task_run_completed', 'system', null, 'retention_task', 'finder_evidence_cleanup', 'private-correlation' ) );
	}
}
