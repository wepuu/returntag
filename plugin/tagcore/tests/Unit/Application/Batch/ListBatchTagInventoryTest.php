<?php
/**
 * RT-206 Batch Tag inventory application tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Batch\BatchTagInventoryPage;
use ReturnTag\TagCore\Application\Batch\Exception\BatchTagInventoryNotFound;
use ReturnTag\TagCore\Application\Batch\Exception\BatchTagInventoryUnavailable;
use ReturnTag\TagCore\Application\Batch\ListBatchTagInventory;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Record\BatchRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewBatchRecord;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\SmartNetwork;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryBatchRepository;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryBatchTagInventoryReader;

/**
 * Verifies complete-inventory state gating.
 */
final class ListBatchTagInventoryTest extends TestCase {
	/**
	 * Complete generated and later terminal states may read the inventory.
	 *
	 * @param BatchStatus $status Batch state.
	 * @dataProvider complete_statuses
	 */
	public function test_lists_only_complete_inventory( BatchStatus $status ): void {
		$batches             = new InMemoryBatchRepository();
		$batches->records[1] = $this->batch( $status, 2, 2 );
		$page                = new BatchTagInventoryPage( array(), null );
		$reader              = new InMemoryBatchTagInventoryReader( $page );
		$service             = new ListBatchTagInventory( $batches, $reader );

		self::assertSame( $page, $service->execute( 1, null, new PageSize( 50 ) ) );
		self::assertSame( 1, $reader->calls );
	}

	/**
	 * Draft, generating, and incomplete terminal Batches must fail closed.
	 *
	 * @param BatchStatus $status Batch state.
	 * @param int         $generated_quantity Generated count.
	 * @dataProvider unavailable_states
	 */
	public function test_rejects_incomplete_inventory(
		BatchStatus $status,
		int $generated_quantity
	): void {
		$batches             = new InMemoryBatchRepository();
		$batches->records[1] = $this->batch( $status, 2, $generated_quantity );
		$reader              = new InMemoryBatchTagInventoryReader( new BatchTagInventoryPage( array(), null ) );
		$service             = new ListBatchTagInventory( $batches, $reader );

		$this->expectException( BatchTagInventoryUnavailable::class );

		try {
			$service->execute( 1, null, new PageSize( 50 ) );
		} finally {
			self::assertSame( 0, $reader->calls );
		}
	}

	/**
	 * Unknown Batch identifiers remain indistinguishable from absent inventory.
	 */
	public function test_rejects_unknown_batch(): void {
		$reader  = new InMemoryBatchTagInventoryReader( new BatchTagInventoryPage( array(), null ) );
		$service = new ListBatchTagInventory( new InMemoryBatchRepository(), $reader );

		$this->expectException( BatchTagInventoryNotFound::class );

		try {
			$service->execute( 99, null, new PageSize( 50 ) );
		} finally {
			self::assertSame( 0, $reader->calls );
		}
	}

	/**
	 * Complete inventory state provider.
	 *
	 * @return array<string, array{BatchStatus}>
	 */
	public function complete_statuses(): array {
		return array(
			'generated' => array( BatchStatus::GENERATED ),
			'exported'  => array( BatchStatus::EXPORTED ),
			'released'  => array( BatchStatus::RELEASED ),
			'suspended' => array( BatchStatus::SUSPENDED ),
			'voided'    => array( BatchStatus::VOIDED ),
		);
	}

	/**
	 * Incomplete inventory state provider.
	 *
	 * @return array<string, array{BatchStatus, int}>
	 */
	public function unavailable_states(): array {
		return array(
			'draft'               => array( BatchStatus::DRAFT, 0 ),
			'generating'          => array( BatchStatus::GENERATING, 1 ),
			'incomplete exported' => array( BatchStatus::EXPORTED, 1 ),
		);
	}

	/**
	 * Build one stored Batch fixture.
	 *
	 * @param BatchStatus $status Batch state.
	 * @param int         $requested_quantity Requested count.
	 * @param int         $generated_quantity Generated count.
	 */
	private function batch(
		BatchStatus $status,
		int $requested_quantity,
		int $generated_quantity
	): BatchRecord {
		$time = new DateTimeImmutable( '2026-07-27 09:00:00', new DateTimeZone( 'UTC' ) );

		return new BatchRecord(
			1,
			new NewBatchRecord(
				'RT-206-UNIT',
				TagType::STICKER,
				null,
				SmartNetwork::NONE,
				null,
				null,
				$requested_quantity,
				$generated_quantity,
				$status,
				false,
				null,
				42,
				$time,
				$time
			)
		);
	}
}
