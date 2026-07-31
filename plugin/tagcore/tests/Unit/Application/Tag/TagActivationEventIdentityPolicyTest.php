<?php
/**
 * RT-307 activation Event identity tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Tag;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Tag\TagActivationEventIdentityPolicy;

/**
 * Verifies the closed activation Event allowlist.
 */
final class TagActivationEventIdentityPolicyTest extends TestCase {
	/**
	 * Only the canonical user-to-Tag identity is accepted.
	 */
	public function test_allows_only_canonical_activation_identity(): void {
		$policy = new TagActivationEventIdentityPolicy();

		self::assertTrue(
			$policy->allows( 'tag_activated', 'user', 42, 'tag', 'A7R2W9', null )
		);
		self::assertFalse(
			$policy->allows( 'tag_activation_conflict', 'user', 42, 'tag', 'A7R2W9', null )
		);
		self::assertFalse(
			$policy->allows( 'tag_activated', 'user', 42, 'tag', 'A7R2W0', null )
		);
		self::assertFalse(
			$policy->allows( 'tag_activated', 'user', null, 'tag', 'A7R2W9', null )
		);
		self::assertFalse(
			$policy->allows( 'tag_activated', 'user', 42, 'tag', 'A7R2W9', 'operation' )
		);
	}
}
