<?php
/**
 * Manual Tag entry use-case tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\PublicTag;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\PublicTag\ManualTagEntryResultState;
use ReturnTag\TagCore\Application\PublicTag\SubmitManualTagEntry;
use ReturnTag\TagCore\Application\Tag\TagIdInputNormalizer;
use ReturnTag\TagCore\Tests\Unit\Application\PublicTag\Fixture\RecordingManualTagEntryRateLimiter;

/**
 * Verifies abuse reservation and canonicalization ordering.
 */
final class SubmitManualTagEntryTest extends TestCase {
	/**
	 * A valid input is reserved and normalized.
	 */
	public function test_reserves_before_returning_a_canonical_tag_id(): void {
		$limiter = new RecordingManualTagEntryRateLimiter( true );
		$result  = $this->service( $limiter )->execute( 'a7-r2 w9', $this->lookup() );

		self::assertSame( ManualTagEntryResultState::ACCEPTED, $result->state );
		self::assertSame( 'A7R2W9', $result->tag_id?->value );
		self::assertSame( 1, $limiter->reservations );
	}

	/**
	 * Invalid input still consumes an abuse budget.
	 */
	public function test_malformed_input_still_consumes_one_budget_reservation(): void {
		$limiter = new RecordingManualTagEntryRateLimiter( true );
		$result  = $this->service( $limiter )->execute( 'invalid', $this->lookup() );

		self::assertSame( ManualTagEntryResultState::INVALID, $result->state );
		self::assertNull( $result->tag_id );
		self::assertSame( 1, $limiter->reservations );
	}

	/**
	 * Throttling stops before input normalization.
	 */
	public function test_throttling_stops_before_normalization(): void {
		$limiter = new RecordingManualTagEntryRateLimiter( false );
		$result  = $this->service( $limiter )->execute( 'a7-r2 w9', $this->lookup() );

		self::assertSame( ManualTagEntryResultState::THROTTLED, $result->state );
		self::assertNull( $result->tag_id );
	}

	/**
	 * Build the test use case.
	 *
	 * @param RecordingManualTagEntryRateLimiter $limiter Recording limiter.
	 */
	private function service( RecordingManualTagEntryRateLimiter $limiter ): SubmitManualTagEntry {
		return new SubmitManualTagEntry(
			new TagIdInputNormalizer(),
			$limiter,
			new class() implements Clock {
				/**
				 * Return the fixed UTC time.
				 */
				public function now(): DateTimeImmutable {
					return new DateTimeImmutable( '2026-08-01 00:00:00', new DateTimeZone( 'UTC' ) );
				}
			}
		);
	}

	/**
	 * Build one deterministic lookup digest.
	 */
	private function lookup(): LookupDigest {
		return LookupDigest::from_digest( hash( 'sha256', 'test-ip' ) );
	}
}
