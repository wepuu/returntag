<?php
/**
 * RT-204 generation chunk tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Batch\BatchGenerationState;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationIntegrityViolation;
use ReturnTag\TagCore\Application\Batch\GenerateBatchChunk;
use ReturnTag\TagCore\Application\Tag\InsertGeneratedTag;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Tests\Fixture\SequenceTagIdGenerator;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\ImmediateTransactionManager;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryBatchGenerationRepository;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryEventRepository;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\RecordingBatchGenerationScheduler;
use ReturnTag\TagCore\Tests\Unit\Application\Tag\Fixture\InMemoryTagRepository;

/**
 * Verifies bounded chunks, resume checkpoints, and terminal behavior.
 */
final class GenerateBatchChunkTest extends TestCase {
	/**
	 * A 205-Tag job resumes as 100, 100, and 5 committed Tags.
	 */
	public function test_generates_205_tags_in_three_resumable_chunks(): void {
		$generation   = new InMemoryBatchGenerationRepository(
			$this->state( BatchStatus::GENERATING, 0, 205 ),
			0
		);
		$events       = new InMemoryEventRepository();
		$transactions = new ImmediateTransactionManager();
		$scheduler    = new RecordingBatchGenerationScheduler();
		$tags         = new InMemoryTagRepository();
		$service      = new GenerateBatchChunk(
			$generation,
			new InsertGeneratedTag( new SequenceTagIdGenerator( $this->tag_ids( 205 ) ), $tags ),
			$events,
			$transactions,
			$scheduler,
			new FixedClock( $this->time() )
		);

		$first  = $service->execute( 7, 0 );
		$second = $service->execute( 7, 100 );
		$third  = $service->execute( 7, 200 );

		self::assertSame( 100, $first->processed_quantity );
		self::assertSame( 100, $first->generated_quantity );
		self::assertTrue( $first->next_scheduled );
		self::assertSame( 100, $second->processed_quantity );
		self::assertSame( 200, $second->generated_quantity );
		self::assertTrue( $second->next_scheduled );
		self::assertSame( 5, $third->processed_quantity );
		self::assertSame( 205, $third->generated_quantity );
		self::assertTrue( $third->completed );
		self::assertFalse( $third->next_scheduled );
		self::assertCount( 205, $tags->records );
		self::assertSame( BatchStatus::GENERATED, $generation->state?->batch_status );
		self::assertSame( 205, $generation->tag_count );
		self::assertSame( array( 100, 200 ), array_column( $scheduler->actions, 'checkpoint' ) );
		self::assertCount( 1, $events->records );
		self::assertSame( 'batch_generation_completed', $events->records[0]->data->event_type );
		self::assertSame( 'system', $events->records[0]->data->actor_type );
		self::assertNull( $events->records[0]->data->actor_id );
		self::assertNull( $events->records[0]->data->metadata->json() );
		self::assertSame( 208, $transactions->calls );
	}

	/**
	 * Replayed completed work is a no-op and cannot append another completion Event.
	 */
	public function test_completed_batch_action_is_idempotent(): void {
		$events    = new InMemoryEventRepository();
		$scheduler = new RecordingBatchGenerationScheduler();
		$service   = new GenerateBatchChunk(
			new InMemoryBatchGenerationRepository(
				$this->state( BatchStatus::GENERATED, 3, 3 ),
				3
			),
			new InsertGeneratedTag( new SequenceTagIdGenerator( array() ), new InMemoryTagRepository() ),
			$events,
			new ImmediateTransactionManager(),
			$scheduler,
			new FixedClock( $this->time() )
		);

		$result = $service->execute( 7, 3 );

		self::assertSame( 0, $result->processed_quantity );
		self::assertTrue( $result->completed );
		self::assertCount( 0, $events->records );
		self::assertCount( 0, $scheduler->actions );
	}

	/**
	 * A checkpoint ahead of committed storage fails closed.
	 */
	public function test_future_checkpoint_is_rejected(): void {
		$service = new GenerateBatchChunk(
			new InMemoryBatchGenerationRepository(
				$this->state( BatchStatus::GENERATING, 20, 100 ),
				20
			),
			new InsertGeneratedTag( new SequenceTagIdGenerator( array() ), new InMemoryTagRepository() ),
			new InMemoryEventRepository(),
			new ImmediateTransactionManager(),
			new RecordingBatchGenerationScheduler(),
			new FixedClock( $this->time() )
		);

		$this->expectException( BatchGenerationIntegrityViolation::class );

		$service->execute( 7, 21 );
	}

	/**
	 * A suspended Batch stops stale queue work without generating or rescheduling.
	 */
	public function test_stale_action_stops_after_status_change(): void {
		$scheduler = new RecordingBatchGenerationScheduler();
		$service   = new GenerateBatchChunk(
			new InMemoryBatchGenerationRepository(
				$this->state( BatchStatus::SUSPENDED, 20, 100 ),
				20
			),
			new InsertGeneratedTag( new SequenceTagIdGenerator( array() ), new InMemoryTagRepository() ),
			new InMemoryEventRepository(),
			new ImmediateTransactionManager(),
			$scheduler,
			new FixedClock( $this->time() )
		);

		$result = $service->execute( 7, 20 );

		self::assertSame( 0, $result->processed_quantity );
		self::assertFalse( $result->completed );
		self::assertFalse( $result->next_scheduled );
		self::assertCount( 0, $scheduler->actions );
	}

	/**
	 * Build one valid generation state.
	 *
	 * @param BatchStatus $status Batch status.
	 * @param int         $generated Generated quantity.
	 * @param int         $requested Requested quantity.
	 */
	private function state( BatchStatus $status, int $generated, int $requested ): BatchGenerationState {
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
	 * Build distinct canonical IDs.
	 *
	 * @param int $count Number of IDs.
	 * @return list<string>
	 */
	private function tag_ids( int $count ): array {
		$alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
		$values   = array();

		for ( $number = 0; $number < $count; ++$number ) {
			$value = '';
			$index = $number;

			for ( $position = 0; $position < 6; ++$position ) {
				$value = $alphabet[ $index % 32 ] . $value;
				$index = intdiv( $index, 32 );
			}

			$values[] = $value;
		}

		return $values;
	}

	/**
	 * Return a fixed UTC time.
	 */
	private function time(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-07-27 08:00:00', new DateTimeZone( 'UTC' ) );
	}
}
