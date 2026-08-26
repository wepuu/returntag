<?php
/**
 * Email delivery transition policy tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Email;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Email\EmailDeliveryTransitionPolicy;
use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;

/** Verifies out-of-order and terminal-state convergence. */
final class EmailDeliveryTransitionPolicyTest extends TestCase {
	/** Older events and terminal regressions must be rejected. */
	public function test_rejects_older_and_terminal_regressions(): void {
		$policy = new EmailDeliveryTransitionPolicy();
		$newer  = new DateTimeImmutable( '2026-08-26T12:00:00Z' );
		$older  = new DateTimeImmutable( '2026-08-26T11:59:59Z' );

		self::assertFalse( $policy->allows( DeliveryStatus::DELIVERED, $newer, DeliveryStatus::DEFERRED, $older ) );
		self::assertFalse( $policy->allows( DeliveryStatus::BOUNCED, $newer, DeliveryStatus::DELIVERED, $newer ) );
		self::assertFalse( $policy->allows( DeliveryStatus::DEFERRED, $older, DeliveryStatus::SENT, $newer ) );
	}

	/** Valid progress and post-delivery complaints must be accepted. */
	public function test_allows_delivery_progress_and_complaint(): void {
		$policy = new EmailDeliveryTransitionPolicy();
		$first  = new DateTimeImmutable( '2026-08-26T12:00:00Z' );
		$later  = new DateTimeImmutable( '2026-08-26T12:01:00Z' );

		self::assertTrue( $policy->allows( DeliveryStatus::SENT, $first, DeliveryStatus::DEFERRED, $later ) );
		self::assertTrue( $policy->allows( DeliveryStatus::DEFERRED, $first, DeliveryStatus::DELIVERED, $later ) );
		self::assertTrue( $policy->allows( DeliveryStatus::DELIVERED, $first, DeliveryStatus::COMPLAINED, $later ) );
	}
}
