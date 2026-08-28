<?php
/**
 * Privacy request Event identity coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Privacy;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Privacy\PrivacyRequestEventIdentityPolicy;

/** Verifies that metadata-free privacy Events cannot carry external identity. */
final class PrivacyRequestEventIdentityPolicyTest extends TestCase {
	/** Only fixed actor, target, result, and correlation combinations pass. */
	public function test_allows_only_fixed_privacy_request_identities(): void {
		$policy = new PrivacyRequestEventIdentityPolicy();

		self::assertTrue( $policy->allows( 'privacy_request_queued', 'user', 42, 'privacy_request', '7', null ) );
		self::assertTrue( $policy->allows( 'privacy_request_queued', 'finder', null, 'privacy_request', '7', null ) );
		self::assertTrue( $policy->allows( 'privacy_request_processing', 'system', null, 'privacy_request', '7', null ) );
		self::assertTrue( $policy->allows( 'privacy_request_completed', 'system', null, 'privacy_request', '7', null ) );
		self::assertFalse( $policy->allows( 'privacy_request_queued', 'user', null, 'privacy_request', '7', null ) );
		self::assertFalse( $policy->allows( 'privacy_request_processing', 'system', null, 'privacy_request', 'email@example.com', null ) );
		self::assertFalse( $policy->allows( 'privacy_request_completed', 'system', null, 'privacy_request', '7', 'private-correlation' ) );
		self::assertFalse( $policy->allows( 'privacy_request_deleted', 'system', null, 'privacy_request', '7', null ) );
	}
}
