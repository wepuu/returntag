<?php
/**
 * Finder Report browser idempotency integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\PublicSite\FinderReportSubmissionClaim;
use ReturnTag\TagCore\PublicSite\WordPressFinderReportSubmissionLedger;
use WP_UnitTestCase;

/** Verifies atomic claims, replay semantics, and bounded cleanup. */
final class FinderReportSubmissionLedgerTest extends WP_UnitTestCase {
	/** A completed token replays as accepted without becoming a second claim. */
	public function test_claim_complete_replay_and_cleanup(): void {
		$ledger = new WordPressFinderReportSubmissionLedger();
		$tag_id = TagId::from_canonical( '234567' );
		$token  = $ledger->issue( $tag_id );

		self::assertSame( FinderReportSubmissionClaim::CLAIMED, $ledger->claim( $tag_id, $token ) );
		self::assertSame( FinderReportSubmissionClaim::INVALID, $ledger->claim( $tag_id, $token ) );

		$ledger->complete( $tag_id, $token, 91 );

		self::assertSame( FinderReportSubmissionClaim::REPLAYED, $ledger->claim( $tag_id, $token ) );
		self::assertSame( 91, $ledger->resolve_report_id( $tag_id, $token ) );
		self::assertNull( $ledger->resolve_report_id( TagId::from_canonical( '234568' ), $token ) );
		self::assertSame(
			FinderReportSubmissionClaim::INVALID,
			$ledger->claim( TagId::from_canonical( '234568' ), $token )
		);

		$ledger->release( $tag_id, $token );
		self::assertSame( FinderReportSubmissionClaim::CLAIMED, $ledger->claim( $tag_id, $token ) );
	}
}
