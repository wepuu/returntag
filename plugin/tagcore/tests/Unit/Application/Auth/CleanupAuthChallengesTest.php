<?php
/**
 * RT-340 bounded authentication challenge cleanup coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Auth;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Auth\AuthChallengeRetentionStore;
use ReturnTag\TagCore\Application\Auth\CleanupAuthChallenges;
use ReturnTag\TagCore\Application\Clock;

/** Verifies that one cleanup run is fixed-time, bounded, and retry-safe. */
final class CleanupAuthChallengesTest extends TestCase {
	/** A short final chunk stops the run and returns the aggregate count. */
	public function test_stops_after_a_short_chunk(): void {
		$now   = new DateTimeImmutable( '2026-08-27 12:00:00', new DateTimeZone( 'UTC' ) );
		$store = $this->store( array( 500, 17 ) );

		$removed = ( new CleanupAuthChallenges( $store, $this->clock( $now ) ) )->execute();

		self::assertSame( 517, $removed );
		self::assertSame(
			array(
				array( $now, CleanupAuthChallenges::CHUNK_SIZE ),
				array( $now, CleanupAuthChallenges::CHUNK_SIZE ),
			),
			$store->calls
		);
	}

	/** A saturated backlog cannot make one request run without a bound. */
	public function test_caps_one_run_at_ten_chunks(): void {
		$now   = new DateTimeImmutable( '2026-08-27 12:00:00', new DateTimeZone( 'UTC' ) );
		$store = $this->store( array_fill( 0, 12, CleanupAuthChallenges::CHUNK_SIZE ) );

		$removed = ( new CleanupAuthChallenges( $store, $this->clock( $now ) ) )->execute();

		self::assertSame( 5000, $removed );
		self::assertCount( CleanupAuthChallenges::MAX_CHUNKS, $store->calls );
	}

	/**
	 * Build a recording retention Store.
	 *
	 * @param array<int, int> $results Per-call affected row counts.
	 */
	private function store( array $results ): AuthChallengeRetentionStore {
		return new class( $results ) implements AuthChallengeRetentionStore {
			/**
			 * Recorded Store calls.
			 *
			 * @var list<array{DateTimeImmutable, int}>
			 */
			public array $calls = array();

			/**
			 * Create the recording Store.
			 *
			 * @param array<int, int> $results Per-call fixture results.
			 */
			public function __construct( private array $results ) {}

			/**
			 * Record one bounded call and return the next fixture result.
			 *
			 * @param DateTimeImmutable $now Current fixed time.
			 * @param int               $limit Requested bound.
			 */
			public function cleanup_eligible( DateTimeImmutable $now, int $limit ): int {
				$this->calls[] = array( $now, $limit );

				return array_shift( $this->results ) ?? 0;
			}
		};
	}

	/**
	 * Build a fixed UTC clock.
	 *
	 * @param DateTimeImmutable $now Fixed time.
	 */
	private function clock( DateTimeImmutable $now ): Clock {
		return new class( $now ) implements Clock {
			/**
			 * Create the fixed clock.
			 *
			 * @param DateTimeImmutable $now Fixed time.
			 */
			public function __construct( private readonly DateTimeImmutable $now ) {}

			/** Return the fixed time. */
			public function now(): DateTimeImmutable {
				return $this->now;
			}
		};
	}
}
