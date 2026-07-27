<?php
/**
 * Batch generation progress query tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Batch\BatchGenerationProgressSnapshot;
use ReturnTag\TagCore\Application\Batch\BatchGenerationQueueState;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationIntegrityViolation;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationNotFound;
use ReturnTag\TagCore\Application\Batch\GetBatchGenerationProgress;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedBatchGenerationQueueMonitor;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryBatchGenerationProgressReader;

/**
 * Verifies stable progress math and queue-state derivation.
 */
final class GetBatchGenerationProgressTest extends TestCase {
	/**
	 * A pristine disabled draft can start and reports no failed Tags.
	 */
	public function test_draft_is_ready_to_start(): void {
		$result = $this->query(
			$this->snapshot( BatchStatus::DRAFT, 1000, 0 ),
			BatchGenerationQueueState::IDLE
		);

		self::assertSame( 1000, $result->remaining_quantity );
		self::assertSame( 0, $result->failed_quantity );
		self::assertSame( 0, $result->progress_percent );
		self::assertSame( BatchGenerationQueueState::IDLE, $result->queue_state );
		self::assertTrue( $result->can_start );
		self::assertFalse( $result->can_retry );
		self::assertSame( 0, $result->poll_after_ms );
	}

	/**
	 * Scheduled work exposes bounded percentage and polling guidance.
	 */
	public function test_scheduled_generation_reports_committed_progress(): void {
		$result = $this->query(
			$this->snapshot( BatchStatus::GENERATING, 1000, 405, $this->utc( '09:00:00' ) ),
			BatchGenerationQueueState::SCHEDULED
		);

		self::assertSame( 595, $result->remaining_quantity );
		self::assertSame( 40, $result->progress_percent );
		self::assertSame( BatchGenerationQueueState::SCHEDULED, $result->queue_state );
		self::assertFalse( $result->can_start );
		self::assertFalse( $result->can_retry );
		self::assertSame( 3000, $result->poll_after_ms );
	}

	/**
	 * A generating Batch without queued work requires an idempotent retry.
	 */
	public function test_idle_generating_batch_needs_attention(): void {
		$result = $this->query(
			$this->snapshot( BatchStatus::GENERATING, 10, 4, $this->utc( '09:00:00' ) ),
			BatchGenerationQueueState::IDLE
		);

		self::assertSame( BatchGenerationQueueState::NEEDS_ATTENTION, $result->queue_state );
		self::assertTrue( $result->can_retry );
		self::assertSame( 0, $result->poll_after_ms );
	}

	/**
	 * Completed storage is terminal regardless of historical queue records.
	 */
	public function test_generated_batch_is_complete(): void {
		$result = $this->query(
			$this->snapshot(
				BatchStatus::GENERATED,
				3,
				3,
				$this->utc( '09:00:00' ),
				$this->utc( '09:05:00' )
			),
			BatchGenerationQueueState::RUNNING
		);

		self::assertSame( 100, $result->progress_percent );
		self::assertSame( 0, $result->remaining_quantity );
		self::assertSame( BatchGenerationQueueState::COMPLETE, $result->queue_state );
		self::assertFalse( $result->can_retry );
		self::assertSame( 0, $result->poll_after_ms );
	}

	/**
	 * Missing audited timestamps fail closed for active generation.
	 */
	public function test_missing_start_event_is_inconsistent(): void {
		$this->expectException( BatchGenerationIntegrityViolation::class );

		$this->query(
			$this->snapshot( BatchStatus::GENERATING, 10, 1 ),
			BatchGenerationQueueState::RUNNING
		);
	}

	/**
	 * Unknown Batches return the existing not-found boundary.
	 */
	public function test_missing_batch_is_not_found(): void {
		$this->expectException( BatchGenerationNotFound::class );

		( new GetBatchGenerationProgress(
			new InMemoryBatchGenerationProgressReader( null ),
			new FixedBatchGenerationQueueMonitor( BatchGenerationQueueState::IDLE )
		) )->execute( 1 );
	}

	/**
	 * Execute the query with fixed adapters.
	 *
	 * @param BatchGenerationProgressSnapshot $snapshot Stored state.
	 * @param BatchGenerationQueueState       $queue_state Provider state.
	 */
	private function query(
		BatchGenerationProgressSnapshot $snapshot,
		BatchGenerationQueueState $queue_state
	): \ReturnTag\TagCore\Application\Batch\BatchGenerationProgress {
		return ( new GetBatchGenerationProgress(
			new InMemoryBatchGenerationProgressReader( $snapshot ),
			new FixedBatchGenerationQueueMonitor( $queue_state )
		) )->execute( $snapshot->batch_id );
	}

	/**
	 * Create one valid stored snapshot.
	 *
	 * @param BatchStatus            $status Batch state.
	 * @param int                    $requested Target quantity.
	 * @param int                    $generated Committed quantity.
	 * @param DateTimeImmutable|null $started_at Generation start.
	 * @param DateTimeImmutable|null $completed_at Generation completion.
	 */
	private function snapshot(
		BatchStatus $status,
		int $requested,
		int $generated,
		?DateTimeImmutable $started_at = null,
		?DateTimeImmutable $completed_at = null
	): BatchGenerationProgressSnapshot {
		return new BatchGenerationProgressSnapshot(
			1,
			$requested,
			$generated,
			$status,
			false,
			$started_at,
			$completed_at,
			$this->utc( '09:10:00' )
		);
	}

	/**
	 * Return a fixed UTC timestamp.
	 *
	 * @param string $time Clock time.
	 */
	private function utc( string $time ): DateTimeImmutable {
		return new DateTimeImmutable( '2026-07-27 ' . $time, new DateTimeZone( 'UTC' ) );
	}
}
