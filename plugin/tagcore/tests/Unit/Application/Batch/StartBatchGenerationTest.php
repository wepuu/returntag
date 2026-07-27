<?php
/**
 * RT-204 generation start tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Batch\BatchGenerationScheduleStatus;
use ReturnTag\TagCore\Application\Batch\BatchGenerationState;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationIntegrityViolation;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationNotAllowed;
use ReturnTag\TagCore\Application\Batch\StartBatchGeneration;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\ImmediateTransactionManager;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryBatchGenerationRepository;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryEventRepository;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\RecordingBatchGenerationScheduler;

/**
 * Verifies start, resume, completion, and fail-closed boundaries.
 */
final class StartBatchGenerationTest extends TestCase {
	/**
	 * A pristine draft becomes generating, records one Event, and queues checkpoint zero.
	 */
	public function test_starts_pristine_draft_atomically_and_queues_first_chunk(): void {
		$generation   = new InMemoryBatchGenerationRepository( $this->state(), 0 );
		$events       = new InMemoryEventRepository();
		$transactions = new ImmediateTransactionManager();
		$scheduler    = new RecordingBatchGenerationScheduler();
		$service      = new StartBatchGeneration(
			$generation,
			$events,
			$transactions,
			$scheduler,
			new FixedClock( $this->time() )
		);

		$result = $service->execute( 7, 42 );

		self::assertSame( BatchStatus::GENERATING, $result->batch_status );
		self::assertSame( BatchGenerationScheduleStatus::QUEUED, $result->schedule_status );
		self::assertSame( 0, $result->generated_quantity );
		self::assertSame( 1, $transactions->calls );
		self::assertCount( 1, $scheduler->actions );
		self::assertSame( 0, $scheduler->actions[0]['checkpoint'] );
		self::assertCount( 1, $events->records );
		self::assertSame( 'batch_generation_started', $events->records[0]->data->event_type );
		self::assertSame( 42, $events->records[0]->data->actor_id );
		self::assertSame( '7', $events->records[0]->data->target_id );
		self::assertNull( $events->records[0]->data->metadata->json() );
	}

	/**
	 * A generating Batch resumes from committed progress without a duplicate Event.
	 */
	public function test_resumes_generating_batch_without_duplicate_start_event(): void {
		$generation = new InMemoryBatchGenerationRepository(
			$this->state( BatchStatus::GENERATING, 125, 250 ),
			125
		);
		$events     = new InMemoryEventRepository();
		$scheduler  = new RecordingBatchGenerationScheduler( BatchGenerationScheduleStatus::ALREADY_SCHEDULED );
		$service    = new StartBatchGeneration(
			$generation,
			$events,
			new ImmediateTransactionManager(),
			$scheduler,
			new FixedClock( $this->time() )
		);

		$result = $service->execute( 7, 42 );

		self::assertSame( BatchStatus::GENERATING, $result->batch_status );
		self::assertSame( BatchGenerationScheduleStatus::ALREADY_SCHEDULED, $result->schedule_status );
		self::assertSame( 125, $scheduler->actions[0]['checkpoint'] );
		self::assertCount( 0, $events->records );
	}

	/**
	 * Completed generation is an idempotent observation with no new queue work.
	 */
	public function test_completed_batch_is_idempotent_without_scheduling(): void {
		$generation = new InMemoryBatchGenerationRepository(
			$this->state( BatchStatus::GENERATED, 250, 250 ),
			250
		);
		$events     = new InMemoryEventRepository();
		$scheduler  = new RecordingBatchGenerationScheduler();
		$service    = new StartBatchGeneration(
			$generation,
			$events,
			new ImmediateTransactionManager(),
			$scheduler,
			new FixedClock( $this->time() )
		);

		$result = $service->execute( 7, 42 );

		self::assertSame( BatchStatus::GENERATED, $result->batch_status );
		self::assertNull( $result->schedule_status );
		self::assertCount( 0, $scheduler->actions );
		self::assertCount( 0, $events->records );
	}

	/**
	 * Released or otherwise ineligible Batches cannot start generation.
	 */
	public function test_rejects_ineligible_batch_status(): void {
		$service = new StartBatchGeneration(
			new InMemoryBatchGenerationRepository( $this->state( BatchStatus::RELEASED ), 0 ),
			new InMemoryEventRepository(),
			new ImmediateTransactionManager(),
			new RecordingBatchGenerationScheduler(),
			new FixedClock( $this->time() )
		);

		$this->expectException( BatchGenerationNotAllowed::class );

		$service->execute( 7, 42 );
	}

	/**
	 * Materialized progress mismatch pauses generation without queue work.
	 */
	public function test_counter_mismatch_fails_closed(): void {
		$scheduler = new RecordingBatchGenerationScheduler();
		$service   = new StartBatchGeneration(
			new InMemoryBatchGenerationRepository(
				$this->state( BatchStatus::GENERATING, 10, 250 ),
				9
			),
			new InMemoryEventRepository(),
			new ImmediateTransactionManager(),
			$scheduler,
			new FixedClock( $this->time() )
		);

		try {
			$service->execute( 7, 42 );
			self::fail( 'Expected inconsistent generation storage to fail.' );
		} catch ( BatchGenerationIntegrityViolation ) {
			self::assertCount( 0, $scheduler->actions );
		}
	}

	/**
	 * Build one valid generation state.
	 *
	 * @param BatchStatus $status Batch status.
	 * @param int         $generated Generated quantity.
	 * @param int         $requested Requested quantity.
	 */
	private function state(
		BatchStatus $status = BatchStatus::DRAFT,
		int $generated = 0,
		int $requested = 250
	): BatchGenerationState {
		return new BatchGenerationState(
			7,
			TagType::CLASSIC_TAG,
			'RT204-MODEL',
			$requested,
			$generated,
			$status,
			false,
			$this->time()
		);
	}

	/**
	 * Return a fixed UTC time.
	 */
	private function time(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-07-27 08:00:00', new DateTimeZone( 'UTC' ) );
	}
}
