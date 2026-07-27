<?php
/**
 * RT-204 Action Scheduler handler tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Queue;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Batch\BatchGenerationState;
use ReturnTag\TagCore\Application\Batch\GenerateBatchChunk;
use ReturnTag\TagCore\Application\Tag\InsertGeneratedTag;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Queue\BatchGenerationActionHandler;
use ReturnTag\TagCore\Tests\Fixture\SequenceTagIdGenerator;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\ImmediateTransactionManager;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryBatchGenerationRepository;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryEventRepository;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\RecordingBatchGenerationScheduler;
use ReturnTag\TagCore\Tests\Unit\Application\Tag\Fixture\InMemoryTagRepository;
use RuntimeException;

/**
 * Verifies bounded retry delays without exposing the underlying failure.
 */
final class BatchGenerationActionHandlerTest extends TestCase {
	/**
	 * A failed action schedules the next bounded retry and throws a fixed message.
	 */
	public function test_failure_schedules_delayed_retry_with_fixed_outer_exception(): void {
		$scheduler = new RecordingBatchGenerationScheduler();
		$handler   = new BatchGenerationActionHandler(
			new GenerateBatchChunk(
				new InMemoryBatchGenerationRepository( $this->state(), 0 ),
				new InsertGeneratedTag(
					new SequenceTagIdGenerator( array() ),
					new InMemoryTagRepository()
				),
				new InMemoryEventRepository(),
				new ImmediateTransactionManager(),
				$scheduler,
				new FixedClock( $this->time() )
			),
			$scheduler
		);

		try {
			$handler->handle( 7, 1, 0 );
			self::fail( 'Expected the generation action to fail.' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'Batch generation chunk failed.', $exception->getMessage() );
			self::assertCount( 1, $scheduler->actions );
			self::assertSame(
				array(
					'batch_id'      => 7,
					'checkpoint'    => 1,
					'retry_attempt' => 1,
					'delay_seconds' => 60,
				),
				$scheduler->actions[0]
			);
		}
	}

	/**
	 * The sixth failure is terminal and does not schedule an unbounded retry.
	 */
	public function test_retry_budget_is_bounded(): void {
		$scheduler = new RecordingBatchGenerationScheduler();
		$handler   = new BatchGenerationActionHandler(
			new GenerateBatchChunk(
				new InMemoryBatchGenerationRepository( $this->state(), 0 ),
				new InsertGeneratedTag(
					new SequenceTagIdGenerator( array() ),
					new InMemoryTagRepository()
				),
				new InMemoryEventRepository(),
				new ImmediateTransactionManager(),
				$scheduler,
				new FixedClock( $this->time() )
			),
			$scheduler
		);

		try {
			$handler->handle( 7, 1, 5 );
			self::fail( 'Expected the generation action to fail.' );
		} catch ( RuntimeException ) {
			self::assertCount( 0, $scheduler->actions );
		}
	}

	/**
	 * Return a generating Batch at checkpoint zero.
	 */
	private function state(): BatchGenerationState {
		return new BatchGenerationState(
			7,
			TagType::CLASSIC_TAG,
			'RT204-MODEL',
			100,
			0,
			BatchStatus::GENERATING,
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
