<?php
/**
 * RT-207 audited Batch export application tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Batch\BatchExportSourceTag;
use ReturnTag\TagCore\Application\Batch\BatchExportState;
use ReturnTag\TagCore\Application\Batch\Exception\BatchExportIntegrityViolation;
use ReturnTag\TagCore\Application\Batch\Exception\BatchExportNotAllowed;
use ReturnTag\TagCore\Application\Batch\ExportBatchCsv;
use ReturnTag\TagCore\Application\Persistence\Record\BatchRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewBatchExportRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewBatchRecord;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\SmartNetwork;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\ImmediateTransactionManager;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryBatchExportRepository;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryBatchExportSourceReader;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryBatchExportWorkflowRepository;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryBatchRepository;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryEventRepository;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\StubBatchExportArtifact;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\StubBatchExportArtifactBuilder;

/**
 * Verifies state, audit, checksum, and anti-regeneration rules.
 */
final class ExportBatchCsvTest extends TestCase {
	private const CHECKSUM = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

	/**
	 * First export appends version one, changes state, and records an Event.
	 */
	public function test_first_export_is_audited_and_marks_batch_exported(): void {
		$fixture = $this->fixture( BatchStatus::GENERATED, self::CHECKSUM );
		$result  = $fixture['service']->execute( 1, 42 );

		self::assertSame( 1, $result->record->data->export_version );
		self::assertSame( 2, $result->record->data->row_count );
		self::assertSame( 'csv', $result->record->data->file_format );
		self::assertSame( self::CHECKSUM, $result->record->data->file_checksum );
		self::assertSame( BatchStatus::EXPORTED, $result->batch_status );
		self::assertSame( 1, $fixture['workflow']->mark_exported_calls );
		self::assertCount( 1, $fixture['events']->records );
		self::assertSame( 'batch_exported', $fixture['events']->records[0]->data->event_type );
		self::assertSame( 42, $fixture['events']->records[0]->data->actor_id );
		self::assertSame( '1', $fixture['events']->records[0]->data->target_id );
		self::assertSame( 1, $fixture['transactions']->calls );
		self::assertFalse( $fixture['artifact']->cleaned );
	}

	/**
	 * Re-export appends a new version only when exact bytes remain unchanged.
	 */
	public function test_reexport_uses_next_version_without_changing_later_state(): void {
		$fixture = $this->fixture( BatchStatus::RELEASED, self::CHECKSUM );
		$fixture['exports']->append(
			new NewBatchExportRecord(
				1,
				1,
				2,
				'csv',
				self::CHECKSUM,
				7,
				$this->time()
			)
		);

		$result = $fixture['service']->execute( 1, 42 );

		self::assertSame( 2, $result->record->data->export_version );
		self::assertSame( BatchStatus::RELEASED, $result->batch_status );
		self::assertSame( 0, $fixture['workflow']->mark_exported_calls );
		self::assertCount( 2, $fixture['exports']->records );
	}

	/**
	 * Re-export fails closed when the exact artifact digest changes.
	 */
	public function test_reexport_rejects_checksum_drift_and_cleans_artifact(): void {
		$fixture = $this->fixture( BatchStatus::EXPORTED, self::CHECKSUM );
		$fixture['exports']->append(
			new NewBatchExportRecord(
				1,
				1,
				2,
				'csv',
				'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
				7,
				$this->time()
			)
		);

		$this->expectException( BatchExportIntegrityViolation::class );

		try {
			$fixture['service']->execute( 1, 42 );
		} finally {
			self::assertTrue( $fixture['artifact']->cleaned );
			self::assertCount( 1, $fixture['exports']->records );
			self::assertCount( 0, $fixture['events']->records );
		}
	}

	/**
	 * Stored quantity drift cannot create an audit record or state transition.
	 */
	public function test_rejects_count_drift_and_cleans_artifact(): void {
		$fixture = $this->fixture( BatchStatus::GENERATED, self::CHECKSUM, 1 );

		$this->expectException( BatchExportIntegrityViolation::class );

		try {
			$fixture['service']->execute( 1, 42 );
		} finally {
			self::assertTrue( $fixture['artifact']->cleaned );
			self::assertCount( 0, $fixture['exports']->records );
			self::assertSame( 0, $fixture['workflow']->mark_exported_calls );
		}
	}

	/**
	 * Suspended and voided manufacturing sets cannot issue new files.
	 *
	 * @param BatchStatus $status Forbidden state.
	 * @dataProvider forbidden_states
	 */
	public function test_rejects_incident_states_before_building( BatchStatus $status ): void {
		$fixture = $this->fixture( $status, self::CHECKSUM );

		$this->expectException( BatchExportNotAllowed::class );

		try {
			$fixture['service']->execute( 1, 42 );
		} finally {
			self::assertFalse( $fixture['artifact']->cleaned );
			self::assertSame( 0, $fixture['transactions']->calls );
		}
	}

	/**
	 * Forbidden-state provider.
	 *
	 * @return array<string, array{BatchStatus}>
	 */
	public function forbidden_states(): array {
		return array(
			'draft'      => array( BatchStatus::DRAFT ),
			'generating' => array( BatchStatus::GENERATING ),
			'suspended'  => array( BatchStatus::SUSPENDED ),
			'voided'     => array( BatchStatus::VOIDED ),
		);
	}

	/**
	 * Build one complete export fixture.
	 *
	 * @param BatchStatus $status Batch state.
	 * @param string      $checksum Artifact checksum.
	 * @param int         $stored_count Stored Tag count.
	 * @return array{
	 *   service: ExportBatchCsv,
	 *   artifact: StubBatchExportArtifact,
	 *   workflow: InMemoryBatchExportWorkflowRepository,
	 *   exports: InMemoryBatchExportRepository,
	 *   events: InMemoryEventRepository,
	 *   transactions: ImmediateTransactionManager
	 * }
	 */
	private function fixture(
		BatchStatus $status,
		string $checksum,
		int $stored_count = 2
	): array {
		$batch = $this->batch( $status );

		$batches             = new InMemoryBatchRepository();
		$batches->records[1] = $batch;

		$artifact = new StubBatchExportArtifact( 2, $checksum );
		$workflow = new InMemoryBatchExportWorkflowRepository(
			new BatchExportState(
				1,
				$batch->data->batch_code,
				$batch->data->tag_type,
				$batch->data->model_code,
				$batch->data->smart_network,
				$batch->data->requested_quantity,
				$batch->data->generated_quantity,
				$status
			),
			$stored_count
		);

		$exports = new InMemoryBatchExportRepository();
		$events  = new InMemoryEventRepository();

		$transactions = new ImmediateTransactionManager();

		$source = new InMemoryBatchExportSourceReader(
			array(
				new BatchExportSourceTag( TagId::from_canonical( '234567' ), TagType::STICKER, 'MODEL-1' ),
				new BatchExportSourceTag( TagId::from_canonical( '234568' ), TagType::STICKER, 'MODEL-1' ),
			)
		);

		$service = new ExportBatchCsv(
			$batches,
			$source,
			new StubBatchExportArtifactBuilder( $artifact ),
			$workflow,
			$exports,
			$events,
			$transactions,
			new FixedClock( $this->time() )
		);

		return compact( 'service', 'artifact', 'workflow', 'exports', 'events', 'transactions' );
	}

	/**
	 * Build one complete Batch.
	 *
	 * @param BatchStatus $status Batch state.
	 */
	private function batch( BatchStatus $status ): BatchRecord {
		return new BatchRecord(
			1,
			new NewBatchRecord(
				'RT-207-UNIT',
				TagType::STICKER,
				'MODEL-1',
				SmartNetwork::NONE,
				null,
				null,
				2,
				2,
				$status,
				false,
				null,
				42,
				$this->time(),
				$this->time()
			)
		);
	}

	/**
	 * Return one fixed UTC time.
	 */
	private function time(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-07-28 10:00:00', new DateTimeZone( 'UTC' ) );
	}
}
