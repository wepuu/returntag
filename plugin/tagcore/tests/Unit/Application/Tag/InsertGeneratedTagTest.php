<?php
/**
 * RT-203 collision retry unit tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Tag;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceConstraintViolationException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceDuplicateKeyException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceException;
use ReturnTag\TagCore\Application\Tag\Exception\TagIdCollisionRetryExhausted;
use ReturnTag\TagCore\Application\Tag\GeneratedTagInput;
use ReturnTag\TagCore\Application\Tag\InsertGeneratedTag;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Tests\Fixture\SequenceTagIdGenerator;
use ReturnTag\TagCore\Tests\Unit\Application\Tag\Fixture\InMemoryTagRepository;

/**
 * Verifies bounded, duplicate-only retry and generated record defaults.
 */
final class InsertGeneratedTagTest extends TestCase {
	/**
	 * A first-attempt insert returns without a collision.
	 */
	public function test_first_candidate_is_inserted_without_retry(): void {
		$generator = new SequenceTagIdGenerator( array( 'N7R2W8' ) );
		$tags      = new InMemoryTagRepository();
		$result    = ( new InsertGeneratedTag( $generator, $tags ) )->execute( $this->input() );

		self::assertSame( 1, $generator->calls );
		self::assertCount( 1, $tags->attempts );
		self::assertSame( 'N7R2W8', $result->tag->data->tag_id );
		self::assertSame( 0, $result->collision_count );
	}

	/**
	 * An explicit duplicate key is retried with a fresh candidate.
	 */
	public function test_duplicate_key_is_retried_until_insert_succeeds(): void {
		$generator = new SequenceTagIdGenerator( array( 'N7R2W8', 'N7R2W9', 'N7R2WA' ) );
		$tags      = new InMemoryTagRepository(
			array(
				'N7R2W8' => new PersistenceDuplicateKeyException( 'Persistence operation failed.' ),
				'N7R2W9' => new PersistenceDuplicateKeyException( 'Persistence operation failed.' ),
			)
		);
		$result    = ( new InsertGeneratedTag( $generator, $tags ) )->execute( $this->input() );

		self::assertSame( 3, $generator->calls );
		self::assertCount( 3, $tags->attempts );
		self::assertSame( 'N7R2WA', $result->tag->data->tag_id );
		self::assertSame( 2, $result->collision_count );
	}

	/**
	 * Generated rows use only server-controlled initial values.
	 */
	public function test_generated_record_uses_unregistered_defaults(): void {
		$generator = new SequenceTagIdGenerator( array( 'N7R2W8' ) );
		$tags      = new InMemoryTagRepository();
		$input     = $this->input();
		$result    = ( new InsertGeneratedTag( $generator, $tags ) )->execute( $input );
		$record    = $result->tag->data;

		self::assertSame( 17, $record->batch_id );
		self::assertNull( $record->owner_id );
		self::assertSame( TagType::SMART_TAG, $record->tag_type );
		self::assertSame( 'SMART-01', $record->model_code );
		self::assertNull( $record->item_name );
		self::assertNull( $record->public_label );
		self::assertSame( TagStatus::UNREGISTERED, $record->tag_status );
		self::assertFalse( $record->lost_mode );
		self::assertNull( $record->lost_message );
		self::assertNull( $record->owner_pairing_ack_at );
		self::assertNull( $record->activated_at );
		self::assertNull( $record->owner_changed_at );
		self::assertNull( $record->last_scanned_at );
		self::assertSame( $input->created_at, $record->created_at );
		self::assertSame( $input->created_at, $record->updated_at );
	}

	/**
	 * Ten collisions fail closed without generating an eleventh candidate.
	 */
	public function test_retry_limit_fails_closed_after_ten_collisions(): void {
		$ids      = $this->ten_ids();
		$failures = array();

		foreach ( $ids as $tag_id ) {
			$failures[ $tag_id ] = new PersistenceDuplicateKeyException( 'Persistence operation failed.' );
		}

		$generator = new SequenceTagIdGenerator( $ids );
		$tags      = new InMemoryTagRepository( $failures );
		$service   = new InsertGeneratedTag( $generator, $tags );

		try {
			$service->execute( $this->input() );
			self::fail( 'Expected collision retry exhaustion.' );
		} catch ( TagIdCollisionRetryExhausted $exception ) {
			self::assertSame( 'Unable to allocate a unique Tag ID.', $exception->getMessage() );
			self::assertSame( InsertGeneratedTag::MAXIMUM_ATTEMPTS, $generator->calls );
			self::assertCount( InsertGeneratedTag::MAXIMUM_ATTEMPTS, $tags->attempts );
			self::assertSame( array(), $tags->records );
		}
	}

	/**
	 * Generic database failures are not collision retries.
	 */
	public function test_generic_persistence_failure_is_not_retried(): void {
		$generator = new SequenceTagIdGenerator( array( 'N7R2W8', 'N7R2W9' ) );
		$tags      = new InMemoryTagRepository(
			array( 'N7R2W8' => new PersistenceException( 'Persistence operation failed.' ) )
		);

		$this->expectException( PersistenceException::class );

		try {
			( new InsertGeneratedTag( $generator, $tags ) )->execute( $this->input() );
		} finally {
			self::assertSame( 1, $generator->calls );
			self::assertCount( 1, $tags->attempts );
		}
	}

	/**
	 * Batch-snapshot failures are not collision retries.
	 */
	public function test_batch_snapshot_failure_is_not_retried(): void {
		$generator = new SequenceTagIdGenerator( array( 'N7R2W8', 'N7R2W9' ) );
		$tags      = new InMemoryTagRepository(
			array(
				'N7R2W8' => new PersistenceConstraintViolationException( 'Referenced record is unavailable or inconsistent.' ),
			)
		);

		$this->expectException( PersistenceConstraintViolationException::class );

		try {
			( new InsertGeneratedTag( $generator, $tags ) )->execute( $this->input() );
		} finally {
			self::assertSame( 1, $generator->calls );
			self::assertCount( 1, $tags->attempts );
		}
	}

	/**
	 * Build one trusted generated Tag input.
	 */
	private function input(): GeneratedTagInput {
		return new GeneratedTagInput(
			17,
			TagType::SMART_TAG,
			'SMART-01',
			new DateTimeImmutable( '2026-07-27 01:02:03', new DateTimeZone( 'UTC' ) )
		);
	}

	/**
	 * Return ten distinct canonical IDs.
	 *
	 * @return list<string>
	 */
	private function ten_ids(): array {
		return array(
			'N7R2W2',
			'N7R2W3',
			'N7R2W4',
			'N7R2W5',
			'N7R2W6',
			'N7R2W7',
			'N7R2W8',
			'N7R2W9',
			'N7R2WA',
			'N7R2WB',
		);
	}
}
