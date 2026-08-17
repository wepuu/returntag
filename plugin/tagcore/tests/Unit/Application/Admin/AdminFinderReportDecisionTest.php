<?php
/**
 * RT-328 decision policy and use-case tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Admin;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Admin\AdminFinderReportAction;
use ReturnTag\TagCore\Application\Admin\AdminFinderReportDecisionEventIdentityPolicy;
use ReturnTag\TagCore\Application\Admin\AdminFinderReportDecisionPolicy;
use ReturnTag\TagCore\Application\Admin\AdminFinderReportDecisionStore;
use ReturnTag\TagCore\Application\Admin\AdminFinderReportState;
use ReturnTag\TagCore\Application\Admin\ManageAdminFinderReportDecision;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderReportStatus;

/** Verifies eligibility, fail-closed controls, and audit identity. */
final class AdminFinderReportDecisionTest extends TestCase {
	/**
	 * Fixed UTC test instant.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $now;

	/** Create the fixed clock value. */
	protected function setUp(): void {
		$this->now = new DateTimeImmutable( '2026-08-17 10:00:00', new DateTimeZone( 'UTC' ) );
	}

	/** Hold and Block use the server-calculated 90-day boundary. */
	public function test_policy_places_fixed_hold_and_blocks_ready_evidence(): void {
		$before  = new AdminFinderReportState( FinderReportStatus::READY, FinderEvidenceStatus::READY, 'queued', 12, $this->now->modify( '+120 days' ), $this->now->modify( '+120 days' ), null, true );
		$policy  = new AdminFinderReportDecisionPolicy();
		$held    = $policy->decide( AdminFinderReportAction::PLACE_HOLD, $before, $this->now );
		$blocked = $policy->decide( AdminFinderReportAction::BLOCK, $before, $this->now );
		self::assertSame( '2026-11-15 10:00:00', $held?->hold_until?->format( 'Y-m-d H:i:s' ) );
		self::assertSame( FinderReportStatus::BLOCKED, $blocked?->report_status );
		self::assertSame( '2026-11-15 10:00:00', $blocked?->hold_until?->format( 'Y-m-d H:i:s' ) );
	}

	/** Release and no-action require an active Hold. */
	public function test_policy_requires_active_hold_for_release_and_no_action(): void {
		$policy = new AdminFinderReportDecisionPolicy();
		$plain  = new AdminFinderReportState( FinderReportStatus::NOTIFIED, FinderEvidenceStatus::READY, 'sent', null, $this->now->modify( '+120 days' ), $this->now->modify( '+120 days' ), null, true );
		$held   = new AdminFinderReportState( FinderReportStatus::NOTIFIED, FinderEvidenceStatus::READY, 'sent', null, $this->now->modify( '+120 days' ), $this->now->modify( '+120 days' ), $this->now->modify( '+1 day' ), true );
		self::assertNull( $policy->decide( AdminFinderReportAction::RELEASE_HOLD, $plain, $this->now ) );
		self::assertNull( $policy->decide( AdminFinderReportAction::RESOLVE_NO_ACTION, $plain, $this->now ) );
		self::assertNull( $policy->decide( AdminFinderReportAction::RELEASE_HOLD, $held, $this->now )?->hold_until );
		self::assertNull( $policy->decide( AdminFinderReportAction::RESOLVE_NO_ACTION, $held, $this->now )?->hold_until );
	}

	/** Ineligible Report or evidence state fails closed. */
	public function test_policy_fails_closed_for_non_ready_or_terminal_reports(): void {
		$policy = new AdminFinderReportDecisionPolicy();
		$future = $this->now->modify( '+120 days' );
		self::assertNull( $policy->decide( AdminFinderReportAction::BLOCK, new AdminFinderReportState( FinderReportStatus::PROCESSING, FinderEvidenceStatus::READY, null, null, $future, $future, null, true ), $this->now ) );
		self::assertNull( $policy->decide( AdminFinderReportAction::BLOCK, new AdminFinderReportState( FinderReportStatus::READY, FinderEvidenceStatus::REJECTED, null, null, $future, $future, null, true ), $this->now ) );
		self::assertNull( $policy->decide( AdminFinderReportAction::BLOCK, new AdminFinderReportState( FinderReportStatus::BLOCKED, FinderEvidenceStatus::READY, null, null, $future, $future, $this->now->modify( '+1 day' ), true ), $this->now ) );
		self::assertNull( $policy->decide( AdminFinderReportAction::BLOCK, new AdminFinderReportState( FinderReportStatus::READY, FinderEvidenceStatus::READY, null, null, $future, $future, null, false ), $this->now ) );
		self::assertNull( $policy->decide( AdminFinderReportAction::BLOCK, new AdminFinderReportState( FinderReportStatus::READY, FinderEvidenceStatus::READY, null, null, $this->now, $future, null, true ), $this->now ) );
	}

	/** The use case stops before persistence while the flag is disabled. */
	public function test_use_case_requires_flag_exact_confirmation_and_operator(): void {
		$store = $this->createMock( AdminFinderReportDecisionStore::class );
		$store->expects( self::never() )->method( 'change' );
		$flags = $this->createMock( FeatureFlagReader::class );
		$flags->expects( self::once() )->method( 'is_enabled' )->with( FeatureFlag::ADMIN_FINDER_REPORT_DECISIONS )->willReturn( false );
		$clock  = $this->createMock( Clock::class );
		$state  = new AdminFinderReportState( FinderReportStatus::READY, FinderEvidenceStatus::READY, null, null, $this->now->modify( '+120 days' ), $this->now->modify( '+120 days' ), null, true );
		$result = ( new ManageAdminFinderReportDecision( $store, $flags, $clock ) )->execute( 12, AdminFinderReportAction::PLACE_HOLD, '12', $state, 9 );
		self::assertFalse( $result->changed );
	}

	/** Only the four numeric-target, user-actor Events are permitted. */
	public function test_audit_identity_is_metadata_free_and_narrow(): void {
		$policy = new AdminFinderReportDecisionEventIdentityPolicy();
		self::assertTrue( $policy->allows( 'finder_report_blocked', 'user', 9, 'finder_report', '12', null ) );
		self::assertFalse( $policy->allows( 'finder_report_blocked', 'system', null, 'finder_report', '12', null ) );
		self::assertFalse( $policy->allows( 'finder_report_message_viewed', 'user', 9, 'finder_report', '12', null ) );
	}
}
