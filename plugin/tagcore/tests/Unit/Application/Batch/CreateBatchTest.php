<?php
/**
 * RT-201 Create Batch application tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Batch\BatchEventIdentityPolicy;
use ReturnTag\TagCore\Application\Batch\CreateBatch;
use ReturnTag\TagCore\Application\Batch\CreateBatchInput;
use ReturnTag\TagCore\Application\Batch\Exception\BatchCodeAlreadyExists;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\SmartNetwork;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\ImmediateTransactionManager;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryBatchRepository;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryEventRepository;

/**
 * Verifies server-controlled defaults and audit behavior.
 */
final class CreateBatchTest extends TestCase {
	/**
	 * Create must persist a disabled draft and one privacy-safe Event atomically.
	 */
	public function test_creates_disabled_draft_and_audit_event_in_one_transaction(): void {
		$batches      = new InMemoryBatchRepository();
		$events       = new InMemoryEventRepository();
		$transactions = new ImmediateTransactionManager();
		$time         = new DateTimeImmutable( '2026-07-24 08:00:00', new DateTimeZone( 'UTC' ) );
		$service      = new CreateBatch( $batches, $events, $transactions, new FixedClock( $time ) );

		$batch = $service->execute( $this->input( 'RT-201-001' ) );

		self::assertSame( 1, $transactions->calls );
		self::assertSame( BatchStatus::DRAFT, $batch->data->batch_status );
		self::assertSame( 0, $batch->data->generated_quantity );
		self::assertFalse( $batch->data->activation_enabled );
		self::assertSame( $time, $batch->data->created_at );
		self::assertSame( $time, $batch->data->updated_at );
		self::assertCount( 1, $events->records );
		self::assertSame( 'batch.created', $events->records[0]->data->event_type );
		self::assertSame( 'user', $events->records[0]->data->actor_type );
		self::assertSame( 42, $events->records[0]->data->actor_id );
		self::assertSame( 'batch', $events->records[0]->data->target_type );
		self::assertSame( '1', $events->records[0]->data->target_id );
		self::assertNull( $events->records[0]->data->metadata->json() );
	}

	/**
	 * Duplicate Batch Codes must fail without another insert or Event.
	 */
	public function test_duplicate_batch_code_fails_before_another_write(): void {
		$batches      = new InMemoryBatchRepository();
		$events       = new InMemoryEventRepository();
		$transactions = new ImmediateTransactionManager();
		$clock        = new FixedClock( new DateTimeImmutable( '2026-07-24 08:00:00', new DateTimeZone( 'UTC' ) ) );
		$service      = new CreateBatch( $batches, $events, $transactions, $clock );
		$input        = $this->input( 'RT-201-DUPLICATE' );

		$service->execute( $input );

		try {
			$service->execute( $input );
			self::fail( 'Expected the duplicate Batch Code to fail.' );
		} catch ( BatchCodeAlreadyExists ) {
			self::assertCount( 1, $batches->records );
			self::assertCount( 1, $events->records );
		}
	}

	/**
	 * Non-Smart Tags cannot persist smart-network descriptors.
	 */
	public function test_non_smart_tag_rejects_smart_network_descriptor(): void {
		$this->expectException( InvalidArgumentException::class );

		new CreateBatchInput(
			'RT-201-INVALID',
			TagType::STICKER,
			null,
			SmartNetwork::APPLE_FIND_MY,
			100,
			null,
			null,
			null,
			42
		);
	}

	/**
	 * Requested quantity cannot exceed the validated production capacity.
	 */
	public function test_requested_quantity_rejects_values_above_supported_capacity(): void {
		$this->expectException( InvalidArgumentException::class );

		new CreateBatchInput(
			'RT-201-TOO-LARGE',
			TagType::STICKER,
			null,
			SmartNetwork::NONE,
			CreateBatchInput::MAX_REQUESTED_QUANTITY + 1,
			null,
			null,
			null,
			42
		);
	}

	/**
	 * The Event policy must allow only approved Batch lifecycle identities.
	 */
	public function test_event_identity_policy_is_narrow(): void {
		$policy = new BatchEventIdentityPolicy();

		self::assertTrue( $policy->allows( 'batch.created', 'user', 42, 'batch', '7', null ) );
		self::assertTrue( $policy->allows( 'batch_generation_started', 'user', 42, 'batch', '7', null ) );
		self::assertTrue( $policy->allows( 'batch_generation_completed', 'system', null, 'batch', '7', null ) );
		self::assertTrue( $policy->allows( 'batch_exported', 'user', 42, 'batch', '7', null ) );
		self::assertTrue( $policy->allows( 'batch_released', 'user', 42, 'batch', '7', null ) );
		self::assertTrue( $policy->allows( 'batch_suspended', 'user', 42, 'batch', '7', null ) );
		self::assertTrue( $policy->allows( 'batch_voided', 'user', 42, 'batch', '7', null ) );
		self::assertFalse( $policy->allows( 'batch.created', 'user', 42, 'batch', '7', 'token-like' ) );
		self::assertFalse( $policy->allows( 'batch.deleted', 'user', 42, 'batch', '7', null ) );
		self::assertFalse( $policy->allows( 'batch.created', 'user', 42, 'batch', 'finder@example.test', null ) );
		self::assertFalse( $policy->allows( 'batch_generation_completed', 'user', 42, 'batch', '7', null ) );
	}

	/**
	 * Build one valid create request.
	 *
	 * @param string $batch_code Unique Batch Code.
	 */
	private function input( string $batch_code ): CreateBatchInput {
		return new CreateBatchInput(
			$batch_code,
			TagType::SMART_TAG,
			'SMART-01',
			SmartNetwork::APPLE_FIND_MY,
			2500,
			'Northstar Manufacturing',
			'direct',
			'Initial production run.',
			42
		);
	}
}
