<?php
/**
 * RT-204 background Batch generation integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use ReturnTag\TagCore\Application\Batch\BatchEventIdentityPolicy;
use ReturnTag\TagCore\Application\Batch\BatchGenerationState;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationIntegrityViolation;
use ReturnTag\TagCore\Application\Batch\GenerateBatchChunk;
use ReturnTag\TagCore\Application\Persistence\DenyAllEventMetadataPolicy;
use ReturnTag\TagCore\Application\Persistence\Record\BatchRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewBatchRecord;
use ReturnTag\TagCore\Application\Tag\InsertGeneratedTag;
use ReturnTag\TagCore\Application\Tag\RandomTagIdGenerator;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\SmartNetwork;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchGenerationRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbEventRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTagRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTransactionManager;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerBatchGenerationScheduler;
use ReturnTag\TagCore\Infrastructure\Random\PhpSecureRandomIntegerSource;
use ReturnTag\TagCore\Tests\Fixture\SequenceTagIdGenerator;
use ReturnTag\TagCore\Tests\Integration\Fixture\RejectingBatchGenerationRepository;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryEventRepository;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\RecordingBatchGenerationScheduler;
use WP_UnitTestCase;
use wpdb;

/**
 * Exercises real MariaDB transactions, row locks, and Action Scheduler uniqueness.
 */
final class BatchGenerationTest extends WP_UnitTestCase {
	/**
	 * Build an isolated current Schema and empty generation queue.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->clear_schema( $wpdb );
		$this->migrate( $wpdb );
		$this->clear_generation_actions();
	}

	/**
	 * Remove isolated fixtures and pending generation work.
	 */
	protected function tearDown(): void {
		global $wpdb;

		$this->clear_generation_actions();
		$this->clear_schema( $wpdb );
		parent::tearDown();
	}

	/**
	 * Real persistence completes 205 Tags as 100, 100, and 5 without duplicates.
	 */
	public function test_real_database_generation_completes_three_resumable_chunks(): void {
		global $wpdb;

		$services = $this->services( $wpdb );
		$batch    = $this->insert_batch( $services['batches'], 205, BatchStatus::GENERATING );

		$first  = $services['generate']->execute( $batch->batch_id, 0 );
		$second = $services['generate']->execute( $batch->batch_id, 100 );
		$third  = $services['generate']->execute( $batch->batch_id, 200 );

		self::assertSame( 100, $first->processed_quantity );
		self::assertSame( 100, $second->processed_quantity );
		self::assertSame( 5, $third->processed_quantity );
		self::assertTrue( $third->completed );

		$tables = new TableNames( $wpdb->prefix );
		$query  = $wpdb->prepare(
			'SELECT batch_status, generated_quantity FROM %i WHERE batch_id = %d',
			$tables->batches(),
			$batch->batch_id
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Isolated RT-204 persistence assertion.
		$stored = $wpdb->get_row( $query, ARRAY_A );

		self::assertIsArray( $stored );
		self::assertSame( 'generated', $stored['batch_status'] );
		self::assertSame( '205', $stored['generated_quantity'] );

		$count_query = $wpdb->prepare(
			'SELECT COUNT(*), COUNT(DISTINCT tag_id) FROM %i WHERE batch_id = %d',
			$tables->tags(),
			$batch->batch_id
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Isolated RT-204 Tag integrity assertion.
		$counts = array_map( 'intval', $wpdb->get_row( $count_query, ARRAY_N ) ?? array() );
		self::assertSame( array( 205, 205 ), $counts );

		$event_query = $wpdb->prepare(
			'SELECT COUNT(*) FROM %i WHERE event_type = %s AND target_id = %s',
			$tables->events(),
			'batch_generation_completed',
			(string) $batch->batch_id
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Isolated RT-204 Event assertion.
		self::assertSame( '1', $wpdb->get_var( $event_query ) );
	}

	/**
	 * A failed conditional counter write rolls back the inserted Tag.
	 */
	public function test_progress_failure_rolls_back_tag_insert(): void {
		global $wpdb;

		$gateway = new WpdbGateway( $wpdb );
		$tables  = new TableNames( $wpdb->prefix );
		$dates   = new DatabaseDateTimeCodec();
		$batches = new WpdbBatchRepository( $gateway, $tables, $dates );
		$batch   = $this->insert_batch( $batches, 1, BatchStatus::GENERATING );
		$state   = new BatchGenerationState(
			$batch->batch_id,
			$batch->data->tag_type,
			$batch->data->model_code,
			1,
			0,
			BatchStatus::GENERATING,
			false,
			$this->utc()
		);
		$service = new GenerateBatchChunk(
			new RejectingBatchGenerationRepository( $state ),
			new InsertGeneratedTag(
				new SequenceTagIdGenerator( array( 'N7R2W8' ) ),
				new WpdbTagRepository( $gateway, $tables, $dates )
			),
			new InMemoryEventRepository(),
			new WpdbTransactionManager( $wpdb ),
			new RecordingBatchGenerationScheduler(),
			new FixedClock( $this->utc() )
		);

		try {
			$service->execute( $batch->batch_id, 0 );
			self::fail( 'Expected atomic progress advancement to fail.' );
		} catch ( BatchGenerationIntegrityViolation ) {
			$count_query = $wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE batch_id = %d',
				$tables->tags(),
				$batch->batch_id
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Isolated rollback assertion.
			self::assertSame( '0', $wpdb->get_var( $count_query ) );
		}
	}

	/**
	 * Action Scheduler rejects duplicate pending actions with the same checkpoint.
	 */
	public function test_action_scheduler_keeps_one_unique_checkpoint_action(): void {
		$scheduler = new ActionSchedulerBatchGenerationScheduler();
		$first     = $scheduler->schedule( 7, 100 );
		$duplicate = $scheduler->schedule( 7, 100 );
		$args      = array(
			'batch_id'      => 7,
			'checkpoint'    => 100,
			'retry_attempt' => 0,
		);

		self::assertSame( 'queued', $first->status->value );
		self::assertSame( 'already_scheduled', $duplicate->status->value );
		self::assertTrue(
			as_has_scheduled_action(
				ActionSchedulerBatchGenerationScheduler::HOOK,
				$args,
				ActionSchedulerBatchGenerationScheduler::GROUP
			)
		);

		$action_id = \ActionScheduler::store()->query_action(
			array(
				'hook'   => ActionSchedulerBatchGenerationScheduler::HOOK,
				'args'   => $args,
				'group'  => ActionSchedulerBatchGenerationScheduler::GROUP,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			)
		);

		self::assertIsInt( $action_id );
		self::assertSame(
			ActionSchedulerBatchGenerationScheduler::PRIORITY,
			\ActionScheduler::store()->fetch_action( $action_id )->get_priority()
		);
	}

	/**
	 * Build production generation services.
	 *
	 * @param wpdb $database Active database adapter.
	 * @return array{batches: WpdbBatchRepository, generate: GenerateBatchChunk}
	 */
	private function services( wpdb $database ): array {
		$gateway   = new WpdbGateway( $database );
		$tables    = new TableNames( $database->prefix );
		$dates     = new DatabaseDateTimeCodec();
		$scheduler = new ActionSchedulerBatchGenerationScheduler();

		return array(
			'batches'  => new WpdbBatchRepository( $gateway, $tables, $dates ),
			'generate' => new GenerateBatchChunk(
				new WpdbBatchGenerationRepository( $gateway, $tables, $dates ),
				new InsertGeneratedTag(
					new RandomTagIdGenerator( new PhpSecureRandomIntegerSource() ),
					new WpdbTagRepository( $gateway, $tables, $dates )
				),
				new WpdbEventRepository(
					$gateway,
					$tables,
					$dates,
					new DenyAllEventMetadataPolicy(),
					new BatchEventIdentityPolicy()
				),
				new WpdbTransactionManager( $database ),
				$scheduler,
				new FixedClock( $this->utc() )
			),
		);
	}

	/**
	 * Insert one isolated Batch fixture.
	 *
	 * @param WpdbBatchRepository $repository Batch Repository.
	 * @param int                 $quantity Requested quantity.
	 * @param BatchStatus         $status Initial status.
	 */
	private function insert_batch(
		WpdbBatchRepository $repository,
		int $quantity,
		BatchStatus $status
	): BatchRecord {
		return $repository->insert(
			new NewBatchRecord(
				'RT204-' . $quantity . '-' . $status->value,
				TagType::CLASSIC_TAG,
				'RT204-MODEL',
				SmartNetwork::NONE,
				'Synthetic Manufacturer',
				'direct',
				$quantity,
				0,
				$status,
				false,
				null,
				7,
				$this->utc(),
				$this->utc()
			)
		);
	}

	/**
	 * Apply all production Migrations.
	 *
	 * @param wpdb $database Active database adapter.
	 */
	private function migrate( wpdb $database ): void {
		$runner = new MigrationRunner(
			( new MigrationRegistryFactory( $database ) )->create(),
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
		);

		self::assertSame( 15, $runner->migrate()->ending_version );
	}

	/**
	 * Remove only trusted ReturnTag tables from the isolated database.
	 *
	 * @param wpdb $database Active database adapter.
	 */
	private function clear_schema( wpdb $database ): void {
		$names = new TableNames( $database->prefix );

		foreach ( array( $names->events(), $names->access_tokens(), $names->messages(), $names->conversations(), $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated cleanup with trusted identifiers.
			$database->query( "DROP TABLE IF EXISTS {$table_name}" );
		}

		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}

	/**
	 * Cancel only pending RT-204 actions in the isolated test site.
	 */
	private function clear_generation_actions(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions(
				'',
				array(),
				ActionSchedulerBatchGenerationScheduler::GROUP
			);
		}
	}

	/**
	 * Return a fixed UTC timestamp.
	 */
	private function utc(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-07-27 09:00:00', new DateTimeZone( 'UTC' ) );
	}
}
